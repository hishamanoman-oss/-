<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Commands\Models\CommandApproval;
use App\Domain\Commands\Models\CommandAttempt;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Commands\Services\Ed25519CommandSigner;
use App\Domain\Commands\Services\RemoteCommandService;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Orders\Models\Order;
use App\Domain\Sales\Models\Product;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Messaging\Events\RemoteCommandUpdated;
use App\Infrastructure\Messaging\Events\SyncBatchCompleted;
use App\Models\User;
use App\Presentation\Livewire\CommandCenterWorkspace;
use Database\Seeders\DevelopmentDemoSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PDO;
use PDOException;
use Tests\TestCase;

final class Phase07CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_ed25519_command_signer_signs_and_detects_tampering(): void
    {
        /** @var Ed25519CommandSigner $signer */
        $signer = app(Ed25519CommandSigner::class);

        $envelope = [
            'command_id' => 101,
            'tenant_id' => 1,
            'data_source_id' => 2,
            'command_type' => 'product.price.update',
            'target_entity_type' => 'product',
            'target_external_id' => '1',
            'payload' => [
                'new_unit_price' => '600.0000',
                'reason' => 'Seasonal adjustment',
            ],
            'precondition' => [
                'unit_price' => '480.0000',
            ],
            'idempotency_key' => 'idem-sign-test-01',
            'expires_at' => '2026-12-01T12:00:00Z',
        ];

        $signature = $signer->signEnvelope($envelope);
        $this->assertNotEmpty($signature);
        $this->assertTrue($signer->verifyEnvelope($envelope, $signature));

        // Different key order still produces the exact same canonical representation
        $reordered = array_reverse($envelope, true);
        $this->assertTrue($signer->verifyEnvelope($reordered, $signature));

        // Tampering payload MUST fail verification
        $tampered = $envelope;
        $tampered['payload']['new_unit_price'] = '1.0000';
        $this->assertFalse($signer->verifyEnvelope($tampered, $signature));

        // Wrong signature MUST fail verification
        $this->assertFalse($signer->verifyEnvelope($envelope, base64_encode(random_bytes(64))));
    }

    public function test_high_risk_price_update_lifecycle_approval_agent_dispatch_ack_and_reconciliation(): void
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
        $product = Product::query()->create([
            'sku' => 'CMD-PRN-001',
            'name' => 'Test Thermal Printer',
            'status' => 'active',
            'base_price' => '480.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '5.0000',
        ]);

        ExternalMapping::query()->create([
            'data_source_id' => $source->id,
            'canonical_entity' => 'product',
            'external_id' => '1',
            'internal_id' => $product->id,
        ]);

        $rawAgentToken = 'rbd_tok_cmd_test_agent_001';
        $instance = ConnectorInstance::query()->create([
            'data_source_id' => $source->id,
            'device_id' => 'cmd-test-device-01',
            'device_name' => 'Command Test Agent',
            'token_hash' => hash('sha256', $rawAgentToken),
            'status' => 'active',
        ]);

        /** @var RemoteCommandService $service */
        $service = app(RemoteCommandService::class);
        Event::fake([RemoteCommandUpdated::class, SyncBatchCompleted::class]);

        // 480 -> 600 (+25%) MUST be marked high risk & require approval
        $cmd = $service->requestCommand(
            actor: $owner,
            source: $source,
            commandType: 'product.price.update',
            targetInternalId: $product->id,
            params: ['new_unit_price' => '600.0000', 'reason' => 'Supplier price increase'],
            idempotencyKey: 'idem-price-600',
        );

        $this->assertSame('pending', $cmd->status);
        $this->assertSame('high', $cmd->risk_level);
        $this->assertTrue($cmd->requires_approval);
        $this->assertSame('1', $cmd->target_external_id);
        $this->assertNotEmpty($cmd->signature_ed25519);

        // Idempotent duplicate request returns existing command without inserting a duplicate
        $dup = $service->requestCommand(
            actor: $owner,
            source: $source,
            commandType: 'product.price.update',
            targetInternalId: $product->id,
            params: ['new_unit_price' => '600.0000', 'reason' => 'Supplier price increase'],
            idempotencyKey: 'idem-price-600',
        );
        $this->assertSame($cmd->id, $dup->id);

        // Approve command
        $approved = $service->approve($cmd, $owner, 'Approved for Q4 catalog update');
        $this->assertSame('approved', $approved->status);
        $this->assertDatabaseHas('command_approvals', [
            'remote_command_id' => $cmd->id,
            'approver_user_id' => $owner->id,
            'decision' => 'approved',
        ]);

        $ctx->clear();

        // Agent GET /api/v1/agent/config exposes public signing key and allowed write commands
        $this->withHeaders(['Authorization' => "Bearer {$rawAgentToken}"])
            ->getJson('/api/v1/agent/config')
            ->assertOk()
            ->assertJsonPath('signing_key_id', config('platform.command_signing_key_id'))
            ->assertJsonPath('allowed_write_commands', ['product.price.update', 'order.status.update', 'inventory.stock.adjust'])
            ->assertJsonPath('command_signing_public_key_ed25519', config('platform.command_signing_public_key'));

        // Agent pulls pending signed commands
        $pullResponse = $this->withHeaders(['Authorization' => "Bearer {$rawAgentToken}"])
            ->getJson('/api/v1/agent/commands/pending')
            ->assertOk()
            ->assertJsonCount(1, 'commands')
            ->assertJsonPath('commands.0.command_id', $cmd->id)
            ->assertJsonPath('commands.0.command_type', 'product.price.update')
            ->assertJsonPath('commands.0.target_external_id', '1');

        $pulledEnvelope = $pullResponse->json('commands.0');
        /** @var Ed25519CommandSigner $signer */
        $signer = app(Ed25519CommandSigner::class);
        $sig = $pulledEnvelope['signature_ed25519'];
        unset($pulledEnvelope['signature_ed25519'], $pulledEnvelope['signing_key_id']);
        $this->assertTrue($signer->verifyEnvelope($pulledEnvelope, $sig));

        $this->assertDatabaseHas('remote_commands', [
            'id' => $cmd->id,
            'status' => 'dispatched',
            'connector_instance_id' => $instance->id,
        ]);
        $this->assertDatabaseHas('command_attempts', [
            'remote_command_id' => $cmd->id,
            'attempt_number' => 1,
            'status' => 'dispatched',
        ]);

        // Second pull does NOT re-dispatch already dispatched command
        $this->withHeaders(['Authorization' => "Bearer {$rawAgentToken}"])
            ->getJson('/api/v1/agent/commands/pending')
            ->assertOk()
            ->assertJsonCount(0, 'commands');

        // Agent sends executing ack
        $this->withHeaders(['Authorization' => "Bearer {$rawAgentToken}"])
            ->postJson("/api/v1/agent/commands/{$cmd->id}/ack", [
                'status' => 'executing',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'executing');

        // Agent sends succeeded ack -> canonical Product base_price reconciled immediately
        $this->withHeaders(['Authorization' => "Bearer {$rawAgentToken}"])
            ->postJson("/api/v1/agent/commands/{$cmd->id}/ack", [
                'status' => 'succeeded',
                'result' => [
                    'confirmed_unit_price' => '600.0000',
                    'affected_rows' => 1,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        $this->assertDatabaseHas('remote_commands', [
            'id' => $cmd->id,
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'base_price' => '600.0000',
        ]);
        $this->assertDatabaseHas('command_attempts', [
            'remote_command_id' => $cmd->id,
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenantA->id,
            'action' => 'command.succeeded',
            'entity_id' => $cmd->id,
        ]);

        Event::assertDispatched(RemoteCommandUpdated::class);
        Event::assertDispatched(SyncBatchCompleted::class);
    }

    public function test_low_risk_price_update_auto_approves_and_expired_commands_are_blocked(): void
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

        $product = Product::query()->create([
            'sku' => 'CMD-LOW-002',
            'name' => 'Receipt Paper Roll',
            'status' => 'active',
            'base_price' => '100.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '10.0000',
        ]);

        ExternalMapping::query()->create([
            'data_source_id' => $source->id,
            'canonical_entity' => 'product',
            'external_id' => '2',
            'internal_id' => $product->id,
        ]);

        /** @var RemoteCommandService $service */
        $service = app(RemoteCommandService::class);
        // +10% (<= 15%) -> low risk -> immediately approved
        $lowRiskCmd = $service->requestCommand(
            actor: $owner,
            source: $source,
            commandType: 'product.price.update',
            targetInternalId: $product->id,
            params: ['new_unit_price' => '110.0000', 'reason' => 'Minor adjustment'],
        );

        $this->assertSame('low', $lowRiskCmd->risk_level);
        $this->assertFalse($lowRiskCmd->requires_approval);
        $this->assertSame('approved', $lowRiskCmd->status);

        // Expire the command manually and verify agent pull marks it expired & skips dispatch
        $lowRiskCmd->forceFill(['expires_at' => now()->subMinute()])->save();

        $instance = ConnectorInstance::query()->create([
            'data_source_id' => $source->id,
            'device_id' => 'cmd-expiry-dev',
            'device_name' => 'Expiry Dev',
            'token_hash' => hash('sha256', 'rbd_tok_exp_001'),
            'status' => 'active',
        ]);

        $dispatched = $service->pullPendingForAgent($source, $instance);
        $this->assertCount(0, $dispatched);
        $this->assertDatabaseHas('remote_commands', [
            'id' => $lowRiskCmd->id,
            'status' => 'expired',
            'error_code' => 'COMMAND_EXPIRED',
        ]);

        $ctx->clear();
    }

    public function test_inventory_stock_adjust_and_order_status_reconcile_canonical_state(): void
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
        $branch = Branch::query()->firstOrFail();
        $warehouse = Warehouse::query()->where('branch_id', $branch->id)->firstOrFail();

        $product = Product::query()->create([
            'sku' => 'CMD-STK-003',
            'name' => 'Cash Drawer',
            'status' => 'active',
            'base_price' => '320.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '5.0000',
        ]);

        $balance = InventoryBalance::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'on_hand' => '15.0000',
            'reserved' => '0.0000',
        ]);

        ExternalMapping::query()->create([
            'data_source_id' => $source->id,
            'canonical_entity' => 'inventory_balance',
            'external_id' => '103:1',
            'internal_id' => $balance->id,
        ]);

        $instance = ConnectorInstance::query()->create([
            'data_source_id' => $source->id,
            'device_id' => 'cmd-stock-dev',
            'device_name' => 'Stock Dev',
            'token_hash' => hash('sha256', 'rbd_tok_stk_001'),
            'status' => 'active',
        ]);

        /** @var RemoteCommandService $service */
        $service = app(RemoteCommandService::class);
        $stockCmd = $service->requestCommand(
            actor: $owner,
            source: $source,
            commandType: 'inventory.stock.adjust',
            targetInternalId: $balance->id,
            params: ['delta' => '5', 'reason' => 'Cycle count gain'],
        );

        $this->assertSame('high', $stockCmd->risk_level);
        $this->assertSame('pending', $stockCmd->status);
        $service->approve($stockCmd, $owner, 'Cycle count verified');
        $service->pullPendingForAgent($source, $instance);

        $service->acknowledgeFromAgent($source, $instance, $stockCmd->fresh(), [
            'status' => 'succeeded',
            'result' => ['confirmed_qty_on_hand' => '20.0000'],
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'id' => $balance->id,
            'on_hand' => '20.0000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'idempotency_key' => "cmd-reconcile:{$stockCmd->id}:{$stockCmd->idempotency_key}",
        ]);

        $order = Order::query()->firstOrFail();
        ExternalMapping::query()->create([
            'data_source_id' => $source->id,
            'canonical_entity' => 'order',
            'external_id' => '501',
            'internal_id' => $order->id,
        ]);

        $orderCmd = $service->requestCommand(
            actor: $owner,
            source: $source,
            commandType: 'order.status.update',
            targetInternalId: $order->id,
            params: ['new_inv_status' => 'refunded', 'reason' => 'Customer return approved'],
        );

        $this->assertSame('high', $orderCmd->risk_level);
        $service->approve($orderCmd, $owner, 'Return authorized');
        $service->pullPendingForAgent($source, $instance);
        $service->acknowledgeFromAgent($source, $instance, $orderCmd->fresh(), [
            'status' => 'succeeded',
            'result' => ['confirmed_inv_status' => 'refunded'],
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'completed',
            'payment_status' => 'refunded',
            'fulfillment_status' => 'returned',
        ]);

        $ctx->clear();
    }

    public function test_rbac_rejects_unauthorized_roles_and_cross_tenant_agent_access(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();
        $inventoryUser = User::query()->where('email', 'inventory@alnoor.local')->firstOrFail();
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();

        // Observer cannot access /commands route
        $this->actingAs($observer)
            ->get('/commands')
            ->assertStatus(403);

        // Inventory manager can view /commands (commands.request) but CANNOT approve
        $this->actingAs($inventoryUser)
            ->get('/commands')
            ->assertOk();

        $ownerMembership = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $owner->id)
            ->firstOrFail());
        $invMembership = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $inventoryUser->id)
            ->firstOrFail());

        $ctx->bind($tenantA, $ownerMembership);
        $sourceA = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $product = Product::query()->create([
            'sku' => 'CMD-RBAC-004',
            'name' => 'Scanner',
            'status' => 'active',
            'base_price' => '200.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '2.0000',
        ]);
        ExternalMapping::query()->create([
            'data_source_id' => $sourceA->id,
            'canonical_entity' => 'product',
            'external_id' => '4',
            'internal_id' => $product->id,
        ]);

        /** @var RemoteCommandService $service */
        $service = app(RemoteCommandService::class);
        $cmdA = $service->requestCommand(
            actor: $owner,
            source: $sourceA,
            commandType: 'product.price.update',
            targetInternalId: $product->id,
            params: ['new_unit_price' => '300.0000', 'reason' => 'RBAC test'],
        );

        // Inventory user lacks commands.approve
        $ctx->bind($tenantA, $invMembership);
        $this->expectException(DomainException::class);
        try {
            $service->approve($cmdA, $inventoryUser, 'Attempt unauthorized approve');
        } finally {
            // Also verify Agent belonging to Tenant B cannot acknowledge Tenant A command
            $ctx->bind($tenantB);
            $sourceB = DataSource::query()->create([
                'name' => 'Tenant B Agent Source',
                'connector_type' => 'local_mysql_agent',
                'status' => 'active',
                'freshness_status' => 'live',
                'capture_mode' => 'incremental_polling',
                'sync_interval_seconds' => 60,
                'capabilities_json' => ['supports_read' => true, 'supports_write' => true],
            ]);
            $tokenB = 'rbd_tok_tenant_b_isolation_01';
            ConnectorInstance::query()->create([
                'data_source_id' => $sourceB->id,
                'device_id' => 'tenant-b-dev',
                'device_name' => 'Tenant B Dev',
                'token_hash' => hash('sha256', $tokenB),
                'status' => 'active',
            ]);
            $ctx->clear();

            $this->withHeaders(['Authorization' => "Bearer {$tokenB}"])
                ->postJson("/api/v1/agent/commands/{$cmdA->id}/ack", ['status' => 'executing'])
                ->assertStatus(404);
        }
    }

    public function test_livewire_command_center_workspace_submits_and_approves_commands(): void
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
        $product = Product::query()->create([
            'sku' => 'CMD-UI-005',
            'name' => 'UI Label Printer',
            'status' => 'active',
            'base_price' => '400.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '3.0000',
        ]);
        ExternalMapping::query()->create([
            'data_source_id' => $source->id,
            'canonical_entity' => 'product',
            'external_id' => '5',
            'internal_id' => $product->id,
        ]);

        Livewire::actingAs($owner)
            ->test(CommandCenterWorkspace::class)
            ->call('openCreateDrawer')
            ->set('dataSourceId', $source->id)
            ->set('commandType', 'product.price.update')
            ->set('targetInternalId', $product->id)
            ->set('newPrice', '520.00')
            ->set('reason', 'UI command dispatch check')
            ->call('submitCommand')
            ->assertHasNoErrors();

        $cmd = RemoteCommand::query()
            ->where('target_internal_id', $product->id)
            ->firstOrFail();
        $this->assertSame('pending', $cmd->status);
        $this->assertSame('high', $cmd->risk_level);

        Livewire::actingAs($owner)
            ->test(CommandCenterWorkspace::class)
            ->call('approveCommand', $cmd->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $cmd->fresh()->status);
        $ctx->clear();
    }

    public function test_mock_pos_writer_database_account_has_strict_least_privilege_only(): void
    {
        $writeUser = (string) env('MOCK_POS_WRITE_USERNAME', '');
        $writePass = (string) env('MOCK_POS_WRITE_PASSWORD', '');
        $this->assertNotEmpty($writeUser, 'MOCK_POS_WRITE_USERNAME must be configured.');
        $this->assertNotEmpty($writePass, 'MOCK_POS_WRITE_PASSWORD must be configured.');

        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=3307;dbname=mock_pos_db;charset=utf8mb4',
            $writeUser,
            $writePass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Allowed: SELECT + transactional no-op UPDATE on allow-listed tables
        $stmt = $pdo->query('SELECT item_code, unit_price FROM tbl_items WHERE item_code = 1 LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);

        $pdo->beginTransaction();
        $upd = $pdo->prepare('UPDATE tbl_items SET unit_price = :p WHERE item_code = :id');
        $upd->execute([':p' => $row['unit_price'], ':id' => $row['item_code']]);
        $pdo->rollBack();

        // Denied: DELETE on mock_pos_db.tbl_items MUST fail with privilege error
        $deleteDenied = false;
        try {
            $pdo->exec('DELETE FROM tbl_items WHERE item_code = -99999');
        } catch (PDOException) {
            $deleteDenied = true;
        }
        $this->assertTrue($deleteDenied, 'mock_pos_writer must NOT have DELETE privilege.');

        // Denied: DROP TABLE MUST fail
        $dropDenied = false;
        try {
            $pdo->exec('DROP TABLE tbl_items');
        } catch (PDOException) {
            $dropDenied = true;
        }
        $this->assertTrue($dropDenied, 'mock_pos_writer must NOT have DROP privilege.');

        // Denied: Reading platform database MUST fail
        $platformDenied = false;
        try {
            $pdo->query('SELECT COUNT(*) FROM rbd_platform.users');
        } catch (PDOException) {
            $platformDenied = true;
        }
        $this->assertTrue($platformDenied, 'mock_pos_writer must NOT have access to rbd_platform.');
    }
}
