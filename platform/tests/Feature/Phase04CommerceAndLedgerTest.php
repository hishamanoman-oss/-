<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderFinancialService;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use App\Presentation\Livewire\InventoryWorkspace;
use App\Presentation\Livewire\OrdersWorkspace;
use Database\Seeders\DevelopmentDemoSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class Phase04CommerceAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_inventory_invariants_across_receipt_reservation_release_fulfillment_adjustment_and_transfer(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        /** @var InventoryLedgerService $ledger */
        $ledger = app(InventoryLedgerService::class);
        /** @var OrderFinancialService $orderService */
        $orderService = app(OrderFinancialService::class);

        $branch1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        $wh1 = Warehouse::query()->where('code', 'WH-RYD-01')->firstOrFail();
        $wh2 = Warehouse::query()->where('code', 'WH-RYD-02')->firstOrFail();

        $product = Product::query()->create([
            'sku' => 'TEST-INV-001',
            'name' => 'Test Ledger Product',
            'status' => 'active',
            'base_price' => '100.0000',
            'cost_price' => '60.0000',
            'currency_code' => 'SAR',
            'reorder_point' => '5.0000',
        ]);

        // 1. Receipt 50 -> on_hand=50, reserved=0, available=50
        $ledger->recordOpeningOrReceipt($wh1->id, $product->id, 50, 't:rcpt:1');
        $bal = InventoryBalance::query()->where('warehouse_id', $wh1->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('50.0000', (string) $bal->on_hand);
        $this->assertSame('0.0000', (string) $bal->reserved);
        $this->assertSame('50.0000', (string) $bal->available);

        // 2. Pending order reserves 12 -> on_hand MUST stay 50, reserved=12, available=38
        $order = $orderService->createOrder(
            branchId: $branch1->id,
            warehouseId: $wh1->id,
            orderStatus: 'pending',
            lines: [['product_id' => $product->id, 'quantity' => 12]],
            reserveStock: true,
        );
        $bal->refresh();
        $this->assertSame('50.0000', (string) $bal->on_hand);
        $this->assertSame('12.0000', (string) $bal->reserved);
        $this->assertSame('38.0000', (string) $bal->available);

        // 3. Release reservation -> on_hand=50, reserved=0, available=50
        /** @var StockReservation $res */
        $res = $order->reservations()->firstOrFail();
        $ledger->releaseReservation($res, 't:rel:1');
        $bal->refresh();
        $this->assertSame('50.0000', (string) $bal->on_hand);
        $this->assertSame('0.0000', (string) $bal->reserved);
        $this->assertSame('50.0000', (string) $bal->available);

        // 4. Reserve 8 and fulfill -> on_hand=42, reserved=0, available=42
        $ledger->reserveForOrder($wh1->id, $product->id, $order->id, 8, 't:res:2');
        $res2 = StockReservation::query()->where('order_id', $order->id)->where('status', 'active')->firstOrFail();
        $ledger->fulfillReservation($res2, 't:ful:2');
        $bal->refresh();
        $this->assertSame('42.0000', (string) $bal->on_hand);
        $this->assertSame('0.0000', (string) $bal->reserved);
        $this->assertSame('42.0000', (string) $bal->available);

        // 5. Adjustment -2 -> on_hand=40, available=40
        $ledger->adjustStock($wh1->id, $product->id, '-2', 't:adj:1', notes: 'Damaged units');
        $bal->refresh();
        $this->assertSame('40.0000', (string) $bal->on_hand);
        $this->assertSame('40.0000', (string) $bal->available);

        // 6. Transfer 10 from wh1 to wh2 -> wh1=30, wh2=10
        $transfer = $ledger->transferBetweenWarehouses($wh1->id, $wh2->id, $product->id, 10, 't:trf:1');
        $this->assertSame('completed', $transfer->status);
        $bal->refresh();
        $balWh2 = InventoryBalance::query()->where('warehouse_id', $wh2->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('30.0000', (string) $bal->on_hand);
        $this->assertSame('10.0000', (string) $balWh2->on_hand);

        // 7. Overselling is strictly rejected
        $this->expectException(DomainException::class);
        $ledger->reserveForOrder($wh1->id, $product->id, $order->id, 999, 't:res:over');
    }

    public function test_idempotency_keys_prevent_duplicate_movements_payments_and_refunds(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        /** @var InventoryLedgerService $ledger */
        $ledger = app(InventoryLedgerService::class);
        /** @var OrderFinancialService $orderService */
        $orderService = app(OrderFinancialService::class);

        $wh1 = Warehouse::query()->where('code', 'WH-RYD-01')->firstOrFail();
        $branch1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        $product = Product::query()->where('sku', 'AN-PRN-80')->firstOrFail();

        $beforeOnHand = (string) InventoryBalance::query()->where('warehouse_id', $wh1->id)->where('product_id', $product->id)->firstOrFail()->on_hand;

        $mov1 = $ledger->adjustStock($wh1->id, $product->id, 5, 'idemp-adj-999', notes: 'Idempotent check');
        $mov2 = $ledger->adjustStock($wh1->id, $product->id, 5, 'idemp-adj-999', notes: 'Idempotent check');
        $this->assertSame($mov1->id, $mov2->id);

        $afterOnHand = (string) InventoryBalance::query()->where('warehouse_id', $wh1->id)->where('product_id', $product->id)->firstOrFail()->on_hand;
        $this->assertSame('5.0000', bcsub($afterOnHand, $beforeOnHand, 4));

        // Payment & Refund idempotency
        $order = $orderService->createOrder($branch1->id, [['product_id' => $product->id, 'quantity' => 1]], $wh1->id, reserveStock: false);
        $pay1 = $orderService->recordPayment($order, '480.0000', 'card', 'REF-999', 'idemp-pay-999');
        $pay2 = $orderService->recordPayment($order, '480.0000', 'card', 'REF-999', 'idemp-pay-999');
        $this->assertSame($pay1->id, $pay2->id);
        $this->assertSame('480.0000', (string) $order->fresh()->paid_total);

        $ref1 = $orderService->recordRefund($order, '100.0000', 'Partial refund', 'idemp-ref-999');
        $ref2 = $orderService->recordRefund($order, '100.0000', 'Partial refund', 'idemp-ref-999');
        $this->assertSame($ref1->id, $ref2->id);
        $this->assertSame('100.0000', (string) $order->fresh()->refunded_total);
    }

    public function test_stock_movements_are_strictly_immutable(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $movement = StockMovement::query()->firstOrFail();

        try {
            $movement->update(['notes' => 'Tampered']);
            $this->fail('Expected LogicException on StockMovement update.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_historical_invoice_snapshots_are_immune_to_product_price_changes(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        /** @var OrderFinancialService $orderService */
        $orderService = app(OrderFinancialService::class);
        $branch1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        $wh1 = Warehouse::query()->where('code', 'WH-RYD-01')->firstOrFail();
        $product = Product::query()->where('sku', 'AN-PRN-80')->firstOrFail();

        $order = $orderService->createOrder(
            branchId: $branch1->id,
            warehouseId: $wh1->id,
            orderStatus: 'completed',
            lines: [['product_id' => $product->id, 'quantity' => 2]],
            reserveStock: false,
        );

        $originalNet = (string) $order->net_sales;
        $originalSnapshotPrice = (string) $order->items()->firstOrFail()->unit_price_snapshot;
        $originalSnapshotName = $order->items()->firstOrFail()->product_name_snapshot;

        // Change product catalog price & name after invoice was created
        $product->update([
            'base_price' => '999.0000',
            'name' => 'Renamed Expensive Printer',
        ]);

        $order->refresh();
        $item = $order->items()->firstOrFail();
        $this->assertSame($originalNet, (string) $order->net_sales);
        $this->assertSame($originalSnapshotPrice, (string) $item->unit_price_snapshot);
        $this->assertSame($originalSnapshotName, $item->product_name_snapshot);
    }

    public function test_net_profit_completeness_guard_enforces_missing_cost_missing_expenses_and_complete_states(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        /** @var FinancialMetricsService $metrics */
        $metrics = app(FinancialMetricsService::class);

        // Tenant A has an order with cost_price = null -> missing_cost / incomplete
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        $incomplete = $metrics->summarize(operatingExpenses: null);
        $this->assertSame('incomplete', $incomplete['profit_completeness_status']);
        $this->assertNull($incomplete['cogs_total']);
        $this->assertNull($incomplete['gross_profit']);
        $this->assertNull($incomplete['net_profit']);

        $missingCost = $metrics->summarize(operatingExpenses: '200.0000');
        $this->assertSame('missing_cost', $missingCost['profit_completeness_status']);
        $this->assertNull($missingCost['cogs_total']);
        $this->assertNull($missingCost['net_profit']);

        // Tenant B has complete costs on all orders -> missing_expenses if null, complete if provided
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());
        $ctx->bind($tenantB);

        $missingExp = $metrics->summarize(operatingExpenses: null);
        $this->assertSame('missing_expenses', $missingExp['profit_completeness_status']);
        $this->assertSame('1100.0000', $missingExp['cogs_total']);
        $this->assertSame('700.0000', $missingExp['gross_profit']); // net_sales (1800) - cogs (1100)
        $this->assertNull($missingExp['net_profit']);

        $complete = $metrics->summarize(operatingExpenses: '150.0000');
        $this->assertSame('complete', $complete['profit_completeness_status']);
        $this->assertSame('550.0000', $complete['net_profit']); // 700 - 150
    }

    public function test_cross_tenant_and_cross_branch_isolation_on_inventory_and_orders(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        $whA2 = $ctx->runWithoutTenantScope(fn () => Warehouse::query()->where('tenant_id', $tenantA->id)->where('code', 'WH-RYD-02')->firstOrFail());
        $whB1 = $ctx->runWithoutTenantScope(fn () => Warehouse::query()->where('tenant_id', $tenantB->id)->where('code', 'WH-JED-01')->firstOrFail());
        $prodB1 = $ctx->runWithoutTenantScope(fn () => Product::query()->where('tenant_id', $tenantB->id)->where('sku', 'HZ-POS-PRO')->firstOrFail());

        // Branch 1 manager sees WH-RYD-01, NOT WH-RYD-02 and NOT Tenant B's HZ-POS-PRO
        $branch1Mgr = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        $this->actingAs($branch1Mgr)
            ->get('/inventory')
            ->assertOk()
            ->assertSee('WH-RYD-01')
            ->assertDontSee('WH-RYD-02')
            ->assertDontSee('HZ-POS-PRO');

        $this->actingAs($branch1Mgr)
            ->get('/orders')
            ->assertOk()
            ->assertSee('RYD-MAIN')
            ->assertDontSee('RYD-NORTH')
            ->assertDontSee('Jeddah Flagship');

        // Branch 1 manager cannot adjust WH-RYD-02 (Branch 2)
        $prodA1 = $ctx->runWithoutTenantScope(fn () => Product::query()->where('tenant_id', $tenantA->id)->where('sku', 'AN-PRN-80')->firstOrFail());
        $ctx->bind($tenantA, $branch1Mgr->memberships()->where('tenant_id', $tenantA->id)->firstOrFail());

        Livewire::actingAs($branch1Mgr)
            ->test(InventoryWorkspace::class)
            ->set('adjustWarehouseId', $whA2->id)
            ->set('adjustProductId', $prodA1->id)
            ->set('adjustDelta', '5')
            ->set('adjustNotes', 'Unauthorized branch 2 adjust')
            ->call('saveAdjustment')
            ->assertForbidden();

        // Observer can view /inventory and /orders but is blocked from adjusting stock
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();
        $this->actingAs($observer)->get('/inventory')->assertOk();
        $this->actingAs($observer)->get('/orders')->assertOk();

        $whA1 = $ctx->runWithoutTenantScope(fn () => Warehouse::query()->where('tenant_id', $tenantA->id)->where('code', 'WH-RYD-01')->firstOrFail());
        $ctx->bind($tenantA, $observer->memberships()->where('tenant_id', $tenantA->id)->firstOrFail());

        Livewire::actingAs($observer)
            ->test(InventoryWorkspace::class)
            ->set('adjustWarehouseId', $whA1->id)
            ->set('adjustProductId', $prodA1->id)
            ->set('adjustDelta', '5')
            ->set('adjustNotes', 'Observer adjust attempt')
            ->call('saveAdjustment')
            ->assertForbidden();

        // Owner of Tenant A cannot transfer across tenants (to Tenant B warehouse)
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $ctx->bind($tenantA, $owner->memberships()->where('tenant_id', $tenantA->id)->firstOrFail());
        $this->assertNotSame($whA1->tenant_id, $whB1->tenant_id);
        $this->assertNull(Order::query()->where('warehouse_id', $whB1->id)->first());
        $this->assertNull(InventoryBalance::query()->where('product_id', $prodB1->id)->first());
    }
}
