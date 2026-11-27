<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Analytics\Services\CustomerIntelligenceService;
use App\Domain\Analytics\Services\InventoryIntelligenceService;
use App\Domain\Analytics\Services\SalesAnalyticsService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Orders\Models\Order;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Sales\Models\Product;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;
use App\Models\User;
use App\Presentation\Livewire\ProductsCatalogWorkspace;
use Carbon\CarbonImmutable;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class Phase06AnalyticsAndWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_sales_analytics_period_comparison_and_top_rankings_with_synced_mock_pos_records(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $connector = new LocalMysqlReadOnlyConnector($source);
        app(SyncIngestionService::class)->syncFromConnector($source, $connector, 'manual');
        $connector->disconnect();

        // Create an order in the previous period (35 days ago) to verify exact delta computation
        $branch1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        Order::query()->create([
            'branch_id' => $branch1->id,
            'order_number' => 'ORD-PREV-TEST-001',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency_code' => 'SAR',
            'gross_sales' => '1000.0000',
            'discount_total' => '0.0000',
            'tax_total' => '150.0000',
            'net_sales' => '1000.0000',
            'paid_total' => '1150.0000',
            'refunded_total' => '0.0000',
            'outstanding_total' => '0.0000',
            'has_complete_cost_data' => false,
            'placed_at' => CarbonImmutable::now()->subDays(35),
        ]);

        $window = AnalyticsPeriod::resolve('30d');
        $result = app(SalesAnalyticsService::class)->analyze(
            tenantId: $tenantA->id,
            allowedBranchIds: null,
            branchId: null,
            period: $window,
        );

        // Independent DB verification using bcmath
        $dbOrders = Order::query()
            ->where('order_status', '!=', 'cancelled')
            ->whereBetween('placed_at', [$window->start, $window->end])
            ->get();

        $expectedNet = DecimalMath::zero();
        foreach ($dbOrders as $o) {
            $oNet = DecimalMath::sub(DecimalMath::sub((string) $o->gross_sales, (string) $o->discount_total), (string) $o->refunded_total);
            $expectedNet = DecimalMath::add($expectedNet, $oNet);
        }

        $this->assertSame($expectedNet, $result['net_sales']);
        $this->assertSame($dbOrders->count(), $result['orders_count']);
        $this->assertSame('1000.0000', $result['previous_net_sales']);
        $this->assertSame(DecimalMath::sub($expectedNet, '1000.0000'), $result['delta_amount']);
        $this->assertNotNull($result['delta_percent']);
        $this->assertNotEmpty($result['top_branches']);
        $this->assertNotEmpty($result['top_products']);
    }

    public function test_inventory_intelligence_low_stock_slow_moving_and_valuation_transparency(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $source = DataSource::query()->where('name', 'Mock Local POS (MySQL)')->firstOrFail();
        $connector = new LocalMysqlReadOnlyConnector($source);
        app(SyncIngestionService::class)->syncFromConnector($source, $connector, 'manual');
        $connector->disconnect();

        $intel = app(InventoryIntelligenceService::class)->analyze(
            tenantId: $tenantA->id,
            allowedBranchIds: null,
            branchId: null,
            period: AnalyticsPeriod::resolve('30d'),
        );

        $this->assertGreaterThan(0, $intel['total_skus']);
        $this->assertGreaterThan(0, $intel['low_stock_count']);
        $this->assertGreaterThan(0, $intel['valuation']['valued_sku_count']);
        // Seeded service/null-cost items with stock or synced null-cost items must be tracked honestly
        $missingProduct = Product::query()->whereNull('cost_price')->first();
        if ($missingProduct !== null && InventoryBalance::query()->where('product_id', $missingProduct->id)->where('on_hand', '>', 0)->exists()) {
            $this->assertTrue($intel['valuation']['has_incomplete_valuation']);
            $this->assertGreaterThan(0, $intel['valuation']['missing_cost_sku_count']);
        }

        // Verify valuation matches independent SUM(on_hand * cost_price) for valued products
        $expectedVal = DecimalMath::zero();
        $balances = InventoryBalance::query()->with('product')->where('on_hand', '>', 0)->get();
        foreach ($balances as $bal) {
            if ($bal->product?->cost_price !== null) {
                $expectedVal = DecimalMath::add($expectedVal, DecimalMath::mul((string) $bal->on_hand, (string) $bal->product->cost_price));
            }
        }
        $this->assertSame($expectedVal, $intel['valuation']['total_valuation']);
    }

    public function test_customer_intelligence_ltv_repeat_buyers_and_receivables(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $intel = app(CustomerIntelligenceService::class)->analyze(
            tenantId: $tenantA->id,
            allowedBranchIds: null,
            branchId: null,
            period: AnalyticsPeriod::resolve('30d'),
        );

        $this->assertGreaterThanOrEqual(2, $intel['total_customers']);
        $this->assertGreaterThanOrEqual(1, $intel['repeat_customers_count']);

        // Customer CUST-AN-001 has Order 1 (pending partial paid) + Order 3 (partially refunded)
        $cust1Row = collect($intel['customers'])->firstWhere('code', 'CUST-AN-001');
        $this->assertNotNull($cust1Row);
        $this->assertSame(2, $cust1Row['orders_count']);
        $this->assertTrue($cust1Row['is_repeat_buyer']);
        $this->assertTrue(DecimalMath::gt($cust1Row['lifetime_value'], '0'));
        $this->assertTrue(DecimalMath::gt($cust1Row['outstanding_total'], '0'));
    }

    public function test_rbac_cost_visibility_branch_and_tenant_isolation(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        // Find Branch 2 order in Tenant A
        $ctx->bind($tenantA);
        $branch2 = Branch::query()->where('code', 'RYD-NORTH')->firstOrFail();
        $branch2Order = Order::query()->where('branch_id', $branch2->id)->firstOrFail();
        $branch1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        $branch1Order = Order::query()->where('branch_id', $branch1->id)->firstOrFail();
        $ctx->clear();

        // 1. Branch 1 manager can view Branch 1 invoice, but gets 403 on Branch 2 invoice
        $branchMgr = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();
        $this->actingAs($branchMgr)
            ->get("/orders/{$branch1Order->id}/invoice")
            ->assertOk()
            ->assertSee($branch1Order->order_number);

        $this->actingAs($branchMgr)
            ->get("/orders/{$branch2Order->id}/invoice")
            ->assertStatus(403);

        // 2. Inventory manager gets 403 on /financials and /customers, but can access /products with cost visible
        $invMgr = User::query()->where('email', 'inventory@alnoor.local')->firstOrFail();
        $this->actingAs($invMgr)->get('/financials')->assertStatus(403);
        $this->actingAs($invMgr)->get('/customers')->assertStatus(403);
        $this->actingAs($invMgr)->get('/products')->assertOk()->assertSee('cost-price-header');

        // 3. Observer cannot see product cost prices in /products
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();
        $this->actingAs($observer)->get('/products')->assertOk()->assertDontSee('cost-price-header');

        $observerMembership = $ctx->runWithoutTenantScope(fn () => TenantMembership::query()
            ->where('tenant_id', $tenantA->id)
            ->where('user_id', $observer->id)
            ->firstOrFail());
        $ctx->bind($tenantA, $observerMembership);
        Livewire::actingAs($observer)
            ->test(ProductsCatalogWorkspace::class)
            ->assertViewHas('canViewCost', false);
        $ctx->clear();

        // 4. Tenant B isolation: Owner switched to Tenant B sees zero Tenant A products/customers/invoices
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenantB->id])
            ->get('/products')
            ->assertOk()
            ->assertSee('HZ-POS-PRO')
            ->assertDontSee('AN-PRN-80');

        $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenantB->id])
            ->get('/customers')
            ->assertOk()
            ->assertSee('Red Sea Hypermarket')
            ->assertDontSee('CUST-AN-001');

        $this->actingAs($owner)
            ->withSession(['active_tenant_id' => $tenantB->id])
            ->get("/orders/{$branch1Order->id}/invoice")
            ->assertStatus(404);
    }
}
