<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Orders\Models\Order;
use App\Domain\Sales\Models\Product;
use App\Domain\Synchronization\Jobs\ProcessSyncBatchJob;
use App\Domain\Synchronization\Models\SyncEvent;
use App\Domain\Synchronization\Models\SyncRun;
use App\Domain\Synchronization\Models\WebhookEvent;
use App\Domain\Synchronization\Services\FreshnessEvaluator;
use App\Domain\Synchronization\Services\ReconciliationService;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;
use App\Infrastructure\Messaging\Events\DataFreshnessUpdated;
use App\Infrastructure\Messaging\Events\SyncBatchCompleted;
use App\Models\User;
use App\Presentation\Livewire\IntegrationCenterWorkspace;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class Phase05AIntegrationAndSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_agent_enrollment_hashes_tokens_updates_heartbeat_and_revocation_returns_401(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();

        $membership = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $owner->id)
            ->firstOrFail());

        $ctx->bind($tenantA, $membership);
        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();

        // Generate one-time enrollment code via Livewire workspace
        $lw = Livewire::actingAs($owner)
            ->test(IntegrationCenterWorkspace::class)
            ->set('newDeviceName', 'Riyadh POS Sync Service')
            ->call('generateEnrollmentCode', $source->id);

        $plainCode = $lw->get('generatedEnrollmentCode');
        $this->assertNotEmpty($plainCode);
        $this->assertStringStartsWith('RBD-', $plainCode);

        $pendingInstance = ConnectorInstance::query()->where('data_source_id', $source->id)->firstOrFail();
        $this->assertSame('pending_enrollment', $pendingInstance->status);
        $this->assertNotSame($plainCode, $pendingInstance->enrollment_code_hash);
        $this->assertSame(hash('sha256', $plainCode), $pendingInstance->enrollment_code_hash);
        $this->assertNull($pendingInstance->token_hash);

        $ctx->clear();

        // Enroll via Agent API
        $enrollRes = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $plainCode,
            'device_id' => 'WIN-SRV-RYD-01',
            'device_name' => 'Riyadh POS Sync Service',
            'agent_version' => '10.0.1',
        ]);

        $enrollRes->assertOk()
            ->assertJsonPath('tenant_id', $tenantA->id)
            ->assertJsonPath('data_source_id', $source->id);

        $bearerToken = $enrollRes->json('bearer_token');
        $this->assertNotEmpty($bearerToken);

        // Verify raw token is NOT stored in database, only SHA-256 hash, and enrollment code cleared
        $enrolled = $ctx->runWithoutTenantScope(fn () => ConnectorInstance::query()->findOrFail($pendingInstance->id));
        $this->assertSame('active', $enrolled->status);
        $this->assertNull($enrolled->enrollment_code_hash);
        $this->assertSame(hash('sha256', $bearerToken), $enrolled->token_hash);
        $this->assertNotSame($bearerToken, $enrolled->token_hash);

        // Reuse of the same one-time enrollment code must fail
        $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $plainCode,
            'device_id' => 'ROGUE-DEVICE',
            'device_name' => 'Rogue',
        ])->assertStatus(401);

        // Heartbeat with bearer token succeeds and updates status
        $this->withHeader('Authorization', 'Bearer '.$bearerToken)
            ->postJson('/api/v1/agent/heartbeat', ['agent_version' => '10.0.2'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data_source_id', $source->id);

        // Fetch remote config & field mappings
        $this->withHeader('Authorization', 'Bearer '.$bearerToken)
            ->getJson('/api/v1/agent/config')
            ->assertOk()
            ->assertJsonPath('data_source.id', $source->id);

        // Revoke token via Livewire UI and ensure immediate 401 on subsequent calls
        $membership = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $owner->id)
            ->firstOrFail());

        $ctx->bind($tenantA, $membership);
        Livewire::actingAs($owner)
            ->test(IntegrationCenterWorkspace::class)
            ->call('revokeInstance', $enrolled->id);
        $ctx->clear();

        $this->withHeader('Authorization', 'Bearer '.$bearerToken)
            ->postJson('/api/v1/agent/heartbeat', [])
            ->assertStatus(401);
    }

    public function test_cross_tenant_api_isolation_and_idempotent_batch_ingestion(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        // Create a DataSource in Tenant B so Tenant A token must never touch it
        $ctx->bind($tenantB);
        $sourceB = DataSource::query()->create([
            'name' => 'Horizon Isolated POS',
            'connector_type' => 'local_mysql_agent',
            'status' => 'active',
            'freshness_status' => 'disconnected',
            'capture_mode' => 'incremental_polling',
            'sync_interval_seconds' => 30,
        ]);

        // Provision Tenant A active instance directly
        $ctx->bind($tenantA);
        $sourceA = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $rawTokenA = bin2hex(random_bytes(32));
        ConnectorInstance::query()->create([
            'data_source_id' => $sourceA->id,
            'device_id' => 'DEV-ISO-A1',
            'device_name' => 'Tenant A Agent',
            'token_hash' => hash('sha256', $rawTokenA),
            'status' => 'active',
        ]);
        $ctx->clear();

        // Client attempts to spoof Tenant B in request payload/headers — server ignores client tenant_id and binds Tenant A
        $payload = [
            'tenant_id' => $tenantB->id,
            'run_type' => 'incremental',
            'events' => [
                [
                    'event_id' => 'evt-iso-prod-001',
                    'entity_type' => 'product',
                    'external_id' => '9901',
                    'payload' => [
                        'item_code' => '9901',
                        'item_sku' => 'iso-pos-9901',
                        'item_title' => 'Isolated Synced Item',
                        'cat_name' => 'Synced Electronics',
                        'unit_price' => '150.00',
                        'cost_price' => '90.00',
                        'is_active' => 1,
                        'updated_at' => '2026-11-26 10:00:00',
                    ],
                    'cursor_table' => 'tbl_items',
                    'cursor_column' => 'updated_at',
                    'cursor_value' => '2026-11-26 10:00:00',
                ],
            ],
        ];

        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawTokenA,
            'Idempotency-Key' => 'batch-key-001',
            'X-Tenant-Id' => (string) $tenantB->id,
        ])->postJson('/api/v1/agent/sync/batches', $payload);

        $res1->assertOk()
            ->assertJsonPath('data_source_id', $sourceA->id)
            ->assertJsonPath('records_upserted', 1)
            ->assertJsonPath('idempotent_replay', false);

        // Verify product exists ONLY in Tenant A, NOT in Tenant B
        $ctx->bind($tenantA);
        $this->assertNotNull(Product::query()->where('sku', 'ISO-POS-9901')->first());
        $ctx->bind($tenantB);
        $this->assertNull(Product::query()->where('sku', 'ISO-POS-9901')->first());
        $this->assertSame(0, SyncRun::query()->where('data_source_id', $sourceB->id)->count());
        $ctx->clear();

        // Replay with same Idempotency-Key returns cached response without re-running
        $resReplay = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawTokenA,
            'Idempotency-Key' => 'batch-key-001',
        ])->postJson('/api/v1/agent/sync/batches', $payload);

        $resReplay->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('sync_run_id', $res1->json('sync_run_id'));

        // Send NEW Idempotency-Key with SAME event_id -> event deduplication skips duplicate
        $resDupEvent = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawTokenA,
            'Idempotency-Key' => 'batch-key-002',
        ])->postJson('/api/v1/agent/sync/batches', $payload);

        $resDupEvent->assertOk()
            ->assertJsonPath('records_upserted', 0)
            ->assertJsonPath('records_skipped_duplicate', 1)
            ->assertJsonPath('idempotent_replay', false);

        $ctx->bind($tenantA);
        $this->assertSame(1, Product::query()->where('sku', 'ISO-POS-9901')->count());
        $this->assertSame(1, SyncEvent::query()->where('event_id', 'evt-iso-prod-001')->count());
        $ctx->clear();

        // Async queue dispatch path
        Queue::fake();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$rawTokenA,
            'Idempotency-Key' => 'batch-key-async-001',
        ])->postJson('/api/v1/agent/sync/batches', array_merge($payload, ['async' => true]))
            ->assertStatus(202)
            ->assertJsonPath('status', 'queued');

        Queue::assertPushed(ProcessSyncBatchJob::class);
    }

    public function test_full_mock_pos_db_readonly_sync_and_zero_drift_reconciliation(): void
    {
        Event::fake([SyncBatchCompleted::class, DataFreshnessUpdated::class]);

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $connector = new LocalMysqlReadOnlyConnector($source);

        $connTest = $connector->testConnection();
        $this->assertTrue($connTest['ok']);

        $schema = $connector->discoverSchema();
        $this->assertArrayHasKey('tbl_branches', $schema);
        $this->assertArrayHasKey('tbl_items', $schema);
        $this->assertArrayHasKey('tbl_invoices', $schema);

        $caps = $connector->inspectCapabilities();
        $this->assertTrue($caps->supportsRead);
        $this->assertFalse($caps->supportsWrite);

        /** @var SyncIngestionService $ingestion */
        $ingestion = app(SyncIngestionService::class);
        $run = $ingestion->syncFromConnector($source, $connector, 'full');
        $connector->disconnect();

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(0, $run->records_failed);
        // 3 branches + 3 warehouses + 16 clients + 22 items + 44 stock + 28 invoices = 116 events
        $this->assertSame(116, $run->records_received);
        $this->assertSame(116, $run->records_upserted);

        $this->assertSame('live', $source->fresh()->freshness_status);
        $this->assertNotNull(Product::query()->where('sku', 'POS-PRN-001')->first());
        $this->assertNotNull(Order::query()->where('order_number', 'POS-2026-0001')->first());

        Event::assertDispatched(SyncBatchCompleted::class);
        Event::assertDispatched(DataFreshnessUpdated::class);

        // Zero-drift reconciliation
        /** @var ReconciliationService $reconciler */
        $reconciler = app(ReconciliationService::class);
        $report = $reconciler->reconcile($source);
        $this->assertTrue($report['in_sync']);
        $this->assertSame(22, $report['entities']['product']['canonical_mapped_count']);
        $this->assertSame(0, $report['entities']['product']['drift']);
        $this->assertSame(28, $report['entities']['order']['canonical_mapped_count']);
        $this->assertSame(0, $report['entities']['order']['drift']);
        $this->assertSame(41, $report['entities']['order_item']['canonical_mapped_count']);
        $this->assertSame(0, $report['entities']['order_item']['drift']);
        $this->assertSame(157, ExternalMapping::query()->where('data_source_id', $source->id)->count());
    }

    public function test_webhook_hmac_verification_and_replay_protection(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);
        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $secret = (string) ($source->config_encrypted['webhook_secret'] ?? '');
        $ctx->clear();

        $body = json_encode([
            'events' => [
                [
                    'event_id' => 'wh-cust-001',
                    'entity_type' => 'customer',
                    'external_id' => 'WH-900',
                    'payload' => [
                        'client_no' => 'WH-900',
                        'full_name' => 'Webhook Customer Ltd',
                        'mobile_no' => '+966500009900',
                        'updated_at' => '2026-11-26 12:00:00',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // Invalid signature -> 401 and logged as rejected
        $this->call('POST', "/api/v1/webhooks/{$source->id}", [], [], [], [
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'deliv-bad-01',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(401);

        $ctx->bind($tenantA);
        $this->assertDatabaseHas('webhook_events', [
            'data_source_id' => $source->id,
            'delivery_id' => 'deliv-bad-01',
            'signature_valid' => false,
            'status' => 'rejected',
        ]);
        $ctx->clear();

        // Valid signature -> 200 processed
        $sig = 'sha256='.hash_hmac('sha256', $body, $secret);
        $this->call('POST', "/api/v1/webhooks/{$source->id}", [], [], [], [
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'deliv-good-01',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk()->assertJsonPath('status', 'processed');

        // Replay same delivery_id -> 200 duplicate without second ingestion
        $this->call('POST', "/api/v1/webhooks/{$source->id}", [], [], [], [
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'deliv-good-01',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk()->assertJsonPath('status', 'duplicate');

        $ctx->bind($tenantA);
        $this->assertSame(1, WebhookEvent::query()->where('delivery_id', 'deliv-good-01')->count());
        $this->assertDatabaseHas('customers', ['tenant_id' => $tenantA->id, 'name' => 'Webhook Customer Ltd']);
    }

    public function test_freshness_evaluator_state_transitions(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $evaluator = app(FreshnessEvaluator::class);
        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();

        // No heartbeats yet -> disconnected
        $this->assertSame('disconnected', $evaluator->evaluate($source));

        // Recent heartbeat & sync -> live
        $now = now();
        $source->forceFill([
            'status' => 'active',
            'sync_interval_seconds' => 30,
            'last_heartbeat_at' => $now->copy()->subSeconds(10),
            'last_successful_sync_at' => $now->copy()->subSeconds(15),
        ])->save();
        $this->assertSame('live', $evaluator->evaluate($source, $now));

        // Heartbeat fresh, last sync older than 2x interval -> delayed
        $source->forceFill([
            'last_heartbeat_at' => $now->copy()->subSeconds(20),
            'last_successful_sync_at' => $now->copy()->subSeconds(80),
        ])->save();
        $this->assertSame('delayed', $evaluator->evaluate($source, $now));

        // Heartbeat stale beyond 3x interval -> disconnected
        $source->forceFill([
            'last_heartbeat_at' => $now->copy()->subSeconds(120),
        ])->save();
        $this->assertSame('disconnected', $evaluator->evaluate($source, $now));

        // Source status error -> error
        $source->forceFill(['status' => 'error'])->save();
        $this->assertSame('error', $evaluator->evaluate($source, $now));
    }
}
