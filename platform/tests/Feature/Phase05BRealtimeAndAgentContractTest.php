<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

final class Phase05BRealtimeAndAgentContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_private_reverb_channel_authorization_allows_member_tenant_and_denies_cross_tenant(): void
    {
        config()->set('broadcasting.default', 'reverb');
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        // branch1@alnoor.local belongs ONLY to Tenant A, NOT Tenant B
        $branchUserA = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        $this->actingAs($branchUserA)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-tenant.{$tenantA->id}",
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        // Cross-tenant channel subscription MUST be strictly rejected with 403
        $this->actingAs($branchUserA)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-tenant.{$tenantB->id}",
            ])
            ->assertStatus(403);
    }

    public function test_dotnet_agent_wire_contract_compatibility_end_to_end(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $plainCode = 'RBD-DOTNET-WIRE01';
        ConnectorInstance::query()->create([
            'data_source_id' => $source->id,
            'device_id' => 'pending-dotnet',
            'device_name' => 'Pending .NET Agent',
            'enrollment_code_hash' => hash('sha256', $plainCode),
            'enrollment_expires_at' => now()->addMinutes(30),
            'status' => 'pending_enrollment',
        ]);
        $ctx->clear();

        // 1. Exact enrollment JSON sent by RemoteSyncAgent
        $enroll = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $plainCode,
            'device_id' => 'WIN10-AGENT-DEV01',
            'device_name' => '.NET 10 Store Agent',
            'agent_version' => '10.0.1',
        ]);
        $enroll->assertOk()
            ->assertJsonStructure(['tenant_id', 'data_source_id', 'connector_instance_id', 'bearer_token']);
        $token = (string) $enroll->json('bearer_token');

        // 2. Exact config JSON read by RemoteSyncAgent
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/agent/config')
            ->assertOk()
            ->assertJsonStructure([
                'data_source' => ['id', 'name', 'connector_type', 'capture_mode', 'sync_interval_seconds', 'freshness_status'],
                'field_mappings',
                'sync_cursors',
            ]);

        // 3. Exact batch payload produced by IncrementalPollingCaptureProvider + SqliteOutboxStore
        $batch = [
            'run_type' => 'incremental',
            'async' => false,
            'events' => [
                [
                    'event_id' => 'branch:1:abc123',
                    'entity_type' => 'branch',
                    'external_id' => '1',
                    'payload' => ['br_id' => 1, 'br_name' => 'فرع الرياض POS', 'br_city' => 'الرياض'],
                    'cursor_table' => 'tbl_branches',
                    'cursor_column' => 'br_id',
                    'cursor_value' => '1',
                ],
                [
                    'event_id' => 'product:501:def456',
                    'entity_type' => 'product',
                    'external_id' => '501',
                    'payload' => [
                        'item_code' => 501,
                        'item_sku' => 'NET10-SKU-501',
                        'item_title' => 'صنف اختبار .NET 10',
                        'cat_name' => 'أجهزة نقاط البيع',
                        'unit_price' => '250.0000',
                        'cost_price' => '140.0000',
                        'is_active' => 1,
                        'updated_at' => '2026-11-27 01:10:00',
                    ],
                    'cursor_table' => 'tbl_items',
                    'cursor_column' => 'updated_at',
                    'cursor_value' => '2026-11-27 01:10:00',
                ],
                [
                    'event_id' => 'order:9001:ghi789',
                    'entity_type' => 'order',
                    'external_id' => '9001',
                    'payload' => [
                        'inv_id' => 9001,
                        'inv_number' => 'POS-CONTRACT-9001',
                        'br_id' => 1,
                        'client_no' => null,
                        'inv_status' => 'completed',
                        'gross_amt' => '500.0000',
                        'disc_amt' => '0.0000',
                        'tax_amt' => '75.0000',
                        'net_amt' => '575.0000',
                        'paid_amt' => '575.0000',
                        'currency_code' => 'SAR',
                        'created_at' => '2026-11-27 01:11:00',
                        'updated_at' => '2026-11-27 01:11:00',
                        'items' => [
                            [
                                'line_id' => 7001,
                                'inv_id' => 9001,
                                'item_code' => 501,
                                'qty' => '2.0000',
                                'unit_price' => '250.0000',
                                'line_total' => '500.0000',
                            ],
                        ],
                    ],
                    'cursor_table' => 'tbl_invoices',
                    'cursor_column' => 'updated_at',
                    'cursor_value' => '2026-11-27 01:11:00',
                ],
            ],
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'agent-contract-batch-001',
        ])->postJson('/api/v1/agent/sync/batches', $batch)
            ->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('records_upserted', 3)
            ->assertJsonPath('records_failed', 0);

        // 4. Exact heartbeat payload sent by SyncWorkerService
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/agent/heartbeat', [
                'agent_version' => '10.0.1',
                'outbox_queue_depth' => 0,
                'last_sync_status' => 'succeeded',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('freshness_status', 'live');

        $ctx->bind($tenantA);
        $this->assertNotNull(Order::query()->where('order_number', 'POS-CONTRACT-9001')->first());
    }
}
