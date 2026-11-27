<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Commands\Services\Ed25519CommandSigner;
use App\Domain\Commands\Services\RemoteCommandService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;
use App\Models\User;
use App\Presentation\Livewire\AlertsCenterWorkspace;
use App\Presentation\Livewire\BranchesManager;
use App\Presentation\Livewire\CommandCenterWorkspace;
use App\Presentation\Livewire\ReportsCenterWorkspace;
use Database\Seeders\DevelopmentDemoSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class Phase09SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_http_security_headers_session_regeneration_and_zero_debug_leakage(): void
    {
        $loginPage = $this->get('/login')->assertOk();
        $loginPage->assertHeader('X-Frame-Options', 'DENY');
        $loginPage->assertHeader('X-Content-Type-Options', 'nosniff');
        $loginPage->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $loginPage->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $csp = (string) $loginPage->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        // API route also includes security headers
        $health = $this->getJson('/api/v1/health')->assertOk();
        $health->assertHeader('X-Frame-Options', 'DENY');
        $health->assertHeader('X-Content-Type-Options', 'nosniff');

        // Login regenerates BOTH session ID and CSRF token
        $this->get('/login');
        $oldSessionId = session()->getId();
        $oldCsrfToken = csrf_token();

        $this->post('/login', [
            'email' => 'owner@demo.local',
            'password' => 'Demo@Phase03!',
        ])->assertRedirect('/dashboard');

        $this->assertNotSame($oldSessionId, session()->getId(), 'Session ID must regenerate on login.');
        $this->assertNotSame($oldCsrfToken, csrf_token(), 'CSRF token must regenerate on login.');

        // Zero debug / stack-trace leakage on API errors when APP_DEBUG=false
        config()->set('app.debug', false);
        $apiErr = $this->postJson('/api/v1/agent/sync/batches', ['events' => []], [
            'Authorization' => 'Bearer invalid_token_probe',
        ]);
        $apiErr->assertStatus(401);
        $body = $apiErr->getContent();
        $this->assertStringNotContainsString('trace', strtolower($body));
        $this->assertStringNotContainsString('vendor\\laravel', $body);
        $this->assertStringNotContainsString('C:\\xampp', $body);
    }

    public function test_rate_limiting_returns_429_on_throttled_endpoints(): void
    {
        // 1. /login throttle (limit 15/min)
        for ($i = 0; $i < 15; $i++) {
            $this->post('/login', [
                'email' => 'throttle-probe@demo.local',
                'password' => 'WrongPass123!',
            ], ['REMOTE_ADDR' => '10.99.1.1'])->assertStatus(302);
        }
        $this->post('/login', [
            'email' => 'throttle-probe@demo.local',
            'password' => 'WrongPass123!',
        ], ['REMOTE_ADDR' => '10.99.1.1'])->assertStatus(429);

        // 2. /api/v1/agent/enroll throttle (limit 10/min)
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.99.2.2'])
                ->postJson('/api/v1/agent/enroll', [
                    'enrollment_code' => 'RBD-INVALID',
                    'device_id' => 'dev-1',
                    'device_name' => 'Dev',
                ]);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.99.2.2'])
            ->postJson('/api/v1/agent/enroll', [
                'enrollment_code' => 'RBD-INVALID',
                'device_id' => 'dev-1',
                'device_name' => 'Dev',
            ])->assertStatus(429);

        // 3. /api/v1/webhooks/{dataSource} throttle (limit 30/min)
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $sourceId = $ctx->runWithoutTenantScope(fn () => DataSource::query()->where('tenant_id', $tenantA->id)->value('id'));

        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.99.3.3'])
                ->withHeaders(['X-Webhook-Delivery-Id' => "deliv-rate-{$i}", 'X-Webhook-Signature' => 'sha256=bad'])
                ->postJson("/api/v1/webhooks/{$sourceId}", ['event' => 'ping']);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.99.3.3'])
            ->withHeaders(['X-Webhook-Delivery-Id' => 'deliv-rate-31', 'X-Webhook-Signature' => 'sha256=bad'])
            ->postJson("/api/v1/webhooks/{$sourceId}", ['event' => 'ping'])
            ->assertStatus(429);

        // 4. /reports/download throttle (limit 20/min)
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($owner)
                ->withServerVariables(['REMOTE_ADDR' => '10.99.4.4'])
                ->get('/reports/download?type=sales_summary&format=csv')
                ->assertOk();
        }
        $this->actingAs($owner)
            ->withServerVariables(['REMOTE_ADDR' => '10.99.4.4'])
            ->get('/reports/download?type=sales_summary&format=csv')
            ->assertStatus(429);
    }

    public function test_adversarial_cross_tenant_idor_spoofing_and_reverb_channel_attacks(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        // Find Tenant B entities
        $ctx->bind($tenantB);
        $branchB = Branch::query()->firstOrFail();
        $orderB = Order::query()->firstOrFail();
        $sourceB = DataSource::query()->create([
            'name' => 'Tenant B Secret Source',
            'connector_type' => 'local_mysql_agent',
            'status' => 'active',
            'freshness_status' => 'live',
            'capture_mode' => 'incremental_polling',
            'sync_interval_seconds' => 30,
            'capabilities_json' => ['supports_read' => true, 'supports_write' => true, 'allowed_write_commands' => ['product.price.update']],
        ]);
        $productB = Product::query()->firstOrFail();
        ExternalMapping::query()->create([
            'data_source_id' => $sourceB->id,
            'canonical_entity' => 'product',
            'external_id' => '999',
            'internal_id' => $productB->id,
        ]);
        $cmdB = RemoteCommand::query()->create([
            'data_source_id' => $sourceB->id,
            'branch_id' => $branchB->id,
            'requested_by_user_id' => User::query()->where('email', 'owner@demo.local')->firstOrFail()->id,
            'command_type' => 'product.price.update',
            'target_entity_type' => 'product',
            'target_internal_id' => $productB->id,
            'target_external_id' => '999',
            'payload_json' => ['new_unit_price' => '1200.0000'],
            'precondition_json' => ['unit_price' => '950.0000'],
            'risk_level' => 'high',
            'requires_approval' => true,
            'status' => 'approved',
            'idempotency_key' => 'idor-cmd-b-001',
            'signature_ed25519' => 'dummy',
            'signing_key_id' => 'cmd-key-v1',
            'expires_at' => now()->addHour(),
        ]);
        $ctx->clear();

        // Branch1 user belongs ONLY to Tenant A
        $userA = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        // Attempt IDOR on Tenant B order invoice & Tenant B branch report download
        $this->actingAs($userA)->get("/orders/{$orderB->id}/invoice")->assertStatus(404);
        $this->actingAs($userA)->get("/reports/download?type=sales_summary&format=csv&branch_id={$branchB->id}")->assertStatus(403);

        // Attempt spoofing X-Tenant-Id header in Agent API using Tenant A connector token
        $ctx->bind($tenantA);
        $sourceA = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $tokenA = 'rbd_tok_adv_agent_a_001';
        ConnectorInstance::query()->create([
            'data_source_id' => $sourceA->id,
            'device_id' => 'adv-agent-a',
            'device_name' => 'Adv Agent A',
            'token_hash' => hash('sha256', $tokenA),
            'status' => 'active',
        ]);
        $ctx->clear();

        // Agent A forging X-Tenant-Id: TenantB CANNOT pull or ack Tenant B command
        $this->withHeaders([
            'Authorization' => "Bearer {$tokenA}",
            'X-Tenant-Id' => (string) $tenantB->id,
        ])->getJson('/api/v1/agent/commands/pending')
            ->assertOk()
            ->assertJsonPath('data_source_id', $sourceA->id)
            ->assertJsonCount(0, 'commands');

        $this->withHeaders([
            'Authorization' => "Bearer {$tokenA}",
            'X-Tenant-Id' => (string) $tenantB->id,
        ])->postJson("/api/v1/agent/commands/{$cmdB->id}/ack", ['status' => 'executing'])
            ->assertStatus(404);

        // Private Reverb channel cross-tenant subscription attack -> 403
        config()->set('broadcasting.default', 'reverb');
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $this->actingAs($userA)->postJson('/broadcasting/auth', [
            'socket_id' => '9999.1111',
            'channel_name' => "private-tenant.{$tenantB->id}",
        ])->assertStatus(403);
    }

    public function test_adversarial_cross_branch_rbac_escalation_and_mid_session_deactivation(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());

        $branch1User = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();
        $inventoryUser = User::query()->where('email', 'inventory@alnoor.local')->firstOrFail();

        $ctx->bind($tenantA);
        $branchNorth = Branch::query()->where('code', 'RYD-NORTH')->firstOrFail();
        $orderNorth = Order::query()->where('branch_id', $branchNorth->id)->first();
        $sourceA = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $ctx->clear();

        // 1. Branch1 user CANNOT download branch2 report or view branch2 invoice or issue branch2 command
        $this->actingAs($branch1User)
            ->get("/reports/download?type=sales_summary&format=csv&branch_id={$branchNorth->id}")
            ->assertStatus(403);

        if ($orderNorth !== null) {
            $this->actingAs($branch1User)->get("/orders/{$orderNorth->id}/invoice")->assertStatus(403);
        }

        // 2. Observer CANNOT mutate branches, commands, alert rules, performance targets, or scheduled reports
        $obsMem = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $observer->id)
            ->firstOrFail());
        $ctx->bind($tenantA, $obsMem);
        Livewire::actingAs($observer)->test(BranchesManager::class)->assertOk()->call('openCreateBranch')->assertForbidden();
        Livewire::actingAs($observer)->test(AlertsCenterWorkspace::class)->assertOk()->call('createRule')->assertForbidden();
        Livewire::actingAs($observer)->test(ReportsCenterWorkspace::class)->assertOk()->call('createTarget')->assertForbidden();
        Livewire::actingAs($observer)->test(ReportsCenterWorkspace::class)->assertOk()->call('createScheduledReport')->assertForbidden();
        Livewire::actingAs($observer)->test(CommandCenterWorkspace::class)->assertForbidden();
        $ctx->clear();

        // 3. Inventory Manager CANNOT access Financials, Customers, Orders, Reports, or Platform Tenants
        foreach (['/financials', '/customers', '/orders', '/reports', '/reports/download?type=sales_summary&format=csv', '/platform/tenants'] as $forbiddenRoute) {
            $this->actingAs($inventoryUser)->get($forbiddenRoute)->assertStatus(403);
        }

        // 4. Mid-session deactivation immediately blocks web & export requests and invalidates session
        $branch1User->forceFill(['is_active' => false])->save();
        $this->actingAs($branch1User)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();

        $branch1User->forceFill(['is_active' => true])->save();
    }

    public function test_xss_sql_injection_csv_formula_injection_and_crypto_replay_defenses(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();

        // 1. XSS escaping test: malicious payload stored in Branch & NotificationRule is HTML-escaped, never raw <script>
        $ctx->bind($tenantA);
        $xssPayload = '<script>window.__xss=1</script>';
        Branch::query()->create([
            'code' => 'XSS-TEST',
            'name' => "Branch {$xssPayload}",
            'city' => 'Riyadh',
            'status' => 'active',
        ]);
        NotificationRule::query()->create([
            'name' => "Rule {$xssPayload}",
            'rule_type' => 'low_stock',
            'threshold_json' => ['min_items' => 1],
            'channel_in_app' => true,
            'channel_email' => false,
            'recipient_roles_json' => ['tenant_owner'],
            'is_active' => true,
        ]);

        // 2. SQL Injection resistance in search filters & LocalMysqlReadOnlyConnector allow-list
        $sqliPayload = "' OR 1=1 --";
        $this->actingAs($owner)->get('/products?search='.urlencode($sqliPayload))->assertOk();
        $this->actingAs($owner)->get('/orders?search='.urlencode($sqliPayload))->assertOk();

        $sourceA = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $connector = new LocalMysqlReadOnlyConnector($sourceA);
        $sqliBlocked = false;
        try {
            $connector->fetchAllFromTable('tbl_items; DROP TABLE users; --');
        } catch (DomainException) {
            $sqliBlocked = true;
        }
        $this->assertTrue($sqliBlocked, 'Non-allowlisted table name MUST be rejected by LocalMysqlReadOnlyConnector.');

        // 3. CSV Formula Injection neutralization
        /** @var ReportExportService $exporter */
        $exporter = app(ReportExportService::class);
        $csv = $exporter->toCsv([
            'report_type' => 'sales_summary',
            'title' => 'Formula Safety Check',
            'subtitle' => 'Test',
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Name', 'NegativeNumber', 'FormulaMinus'],
            'rows' => [
                ['=CMD|" /C calc"!A0', '-150.00', '-2+3+cmd|" /C calc"!A0'],
                ['+SUM(A1:A2)', '@SUM(1,2)', 'Safe Text'],
            ],
            'notes' => [],
        ]);
        $this->assertStringContainsString("'=CMD", $csv);
        $this->assertStringContainsString("'+SUM", $csv);
        $this->assertStringContainsString("'@SUM", $csv);
        $this->assertStringContainsString("'-2+3+cmd", $csv);
        $this->assertStringContainsString(',-150.00,', $csv); // Valid negative numeric decimal preserved safely

        // 4. Forged Webhook HMAC signature -> 401 & replay protection
        $this->withHeaders([
            'X-Webhook-Delivery-Id' => 'adv-deliv-001',
            'X-Webhook-Signature' => 'sha256=deadbeefdeadbeefdeadbeefdeadbeef',
        ])->postJson("/api/v1/webhooks/{$sourceA->id}", ['event' => 'order.paid'])
            ->assertStatus(401);

        // Valid HMAC accepted once, duplicate delivery_id detected as replay
        $payloadJson = json_encode(['event_id' => 'wh-evt-1', 'entity_type' => 'product', 'external_id' => '999', 'payload' => []]);
        $secret = (string) ($sourceA->config_encrypted['webhook_secret'] ?? '');
        $validSig = 'sha256='.hash_hmac('sha256', (string) $payloadJson, $secret);

        $this->call('POST', "/api/v1/webhooks/{$sourceA->id}", [], [], [], [
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'adv-deliv-valid-001',
            'HTTP_X_WEBHOOK_SIGNATURE' => $validSig,
            'CONTENT_TYPE' => 'application/json',
        ], $payloadJson)->assertStatus(200)->assertJsonPath('status', 'processed');

        $this->call('POST', "/api/v1/webhooks/{$sourceA->id}", [], [], [], [
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'adv-deliv-valid-001',
            'HTTP_X_WEBHOOK_SIGNATURE' => $validSig,
            'CONTENT_TYPE' => 'application/json',
        ], $payloadJson)->assertStatus(200)->assertJsonPath('status', 'duplicate');

        // 5. Tampered Ed25519 envelope rejected
        /** @var Ed25519CommandSigner $signer */
        $signer = app(Ed25519CommandSigner::class);
        $env = ['command_id' => 1, 'tenant_id' => $tenantA->id, 'payload' => ['new_unit_price' => '500.0000']];
        $sig = $signer->signEnvelope($env);
        $env['payload']['new_unit_price'] = '0.0100';
        $this->assertFalse($signer->verifyEnvelope($env, $sig));

        // 6. Rendered HTML escapes XSS payload
        $branchResponse = $this->actingAs($owner)->get('/organization/branches')->assertOk();
        $branchResponse->assertDontSee($xssPayload, false);
        $branchResponse->assertSee('&lt;script&gt;window.__xss=1&lt;/script&gt;', false);

        $ctx->clear();
    }

    public function test_concurrency_inventory_invariants_and_zero_drift_raw_sql_financial_parity(): void
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

        $warehouse = Warehouse::query()->firstOrFail();
        $product = Product::query()->create([
            'sku' => 'CONC-INV-009',
            'name' => 'Concurrency Test Item',
            'status' => 'active',
            'base_price' => '100.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '5.0000',
        ]);
        $order = Order::query()->firstOrFail();

        /** @var InventoryLedgerService $ledger */
        $ledger = app(InventoryLedgerService::class);
        $ledger->recordOpeningOrReceipt($warehouse->id, $product->id, '10.0000', 'conc:open:1', 'opening');

        // Duplicate idempotency_key replays return exact existing movement without double-reserving
        $m1 = $ledger->reserveForOrder($warehouse->id, $product->id, $order->id, '4.0000', 'conc:res:1');
        $m2 = $ledger->reserveForOrder($warehouse->id, $product->id, $order->id, '4.0000', 'conc:res:1');
        $this->assertSame($m1->id, $m2->id);

        $bal = InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $this->assertSame('10.0000', (string) $bal->on_hand);
        $this->assertSame('4.0000', (string) $bal->reserved);
        $this->assertSame('6.0000', DecimalMath::sub((string) $bal->on_hand, (string) $bal->reserved));

        // Oversell attempt (>6 remaining available) MUST throw DomainException
        $oversellRejected = false;
        try {
            $ledger->reserveForOrder($warehouse->id, $product->id, $order->id, '7.0000', 'conc:res:oversell');
        } catch (DomainException) {
            $oversellRejected = true;
        }
        $this->assertTrue($oversellRejected, 'Oversell beyond available stock must be rejected.');

        // Independent raw SQL DECIMAL aggregation parity vs ReportExportService::build('sales_summary')
        $rawSql = DB::selectOne(
            'SELECT
                COALESCE(SUM(gross_sales - discount_total - refunded_total), 0) AS raw_net_sales,
                COALESCE(SUM(paid_total), 0) AS raw_paid_total
             FROM orders
             WHERE tenant_id = ? AND order_status != ?',
            [$tenantA->id, 'cancelled']
        );

        /** @var ReportExportService $exporter */
        $exporter = app(ReportExportService::class);
        $report = $exporter->build('sales_summary', $tenantA->id, null, null, '30d');
        $netRow = collect($report['rows'])->first(fn (array $r): bool => $r[0] === 'Net Sales');
        $paidRow = collect($report['rows'])->first(fn (array $r): bool => $r[0] === 'Paid Revenue');

        $this->assertSame(0, DecimalMath::comp((string) $rawSql->raw_net_sales, $netRow[1], 2));
        $this->assertSame(0, DecimalMath::comp((string) $rawSql->raw_paid_total, $paidRow[1], 2));

        $ctx->clear();
    }
}
