<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Services\TenantContext;

final class InventoryIntelligenceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     total_skus: int,
     *     total_on_hand: string,
     *     total_reserved: string,
     *     total_available: string,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     *     slow_moving_count: int,
     *     low_stock_balance_ids: list<int>,
     *     slow_moving_balance_ids: list<int>,
     *     low_stock_items: list<array{balance_id: int, product_id: int, sku: string, product_name: string, warehouse_name: string, warehouse_code: string, available: string, reorder_point: string, is_out_of_stock: bool}>,
     *     slow_moving_items: list<array{balance_id: int, product_id: int, sku: string, product_name: string, warehouse_name: string, on_hand: string}>,
     *     warehouse_summary: list<array{warehouse_id: int, warehouse_name: string, warehouse_code: string, branch_name: string, total_on_hand: string, total_available: string, low_stock_count: int}>,
     *     valuation: array{total_valuation: string, valued_sku_count: int, missing_cost_sku_count: int, has_incomplete_valuation: bool}
     * }
     */
    public function analyze(
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
        ?int $warehouseId = null,
        ?AnalyticsPeriod $period = null,
    ): array {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        $window = $period ?? AnalyticsPeriod::resolve('30d');

        $warehousesQuery = Warehouse::query()
            ->with('branch:id,name,code')
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->when(
                $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                    : $q
            )
            ->when($warehouseId !== null, fn ($q) => $q->where('id', $warehouseId))
            ->orderBy('id');

        $warehouses = $warehousesQuery->get();
        $allowedWarehouseIds = $warehouses->pluck('id')->all();

        $balances = InventoryBalance::query()
            ->with(['product', 'warehouse.branch'])
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->whereIn('warehouse_id', $allowedWarehouseIds === [] ? [-1] : $allowedWarehouseIds)
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        // Product IDs that had completed/confirmed sales in the selected period within allowed branches
        $soldProductIds = OrderItem::query()
            ->whereHas('order', function ($q) use ($resolvedTenantId, $branchId, $allowedBranchIds, $window): void {
                $q->when($resolvedTenantId !== null, fn ($oq) => $oq->where('tenant_id', $resolvedTenantId))
                    ->where('order_status', '!=', 'cancelled')
                    ->where('payment_status', '!=', 'refunded')
                    ->whereBetween('placed_at', [$window->start, $window->end])
                    ->when(
                        $branchId !== null,
                        fn ($oq) => $oq->where('branch_id', $branchId),
                        fn ($oq) => is_array($allowedBranchIds)
                            ? $oq->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                            : $oq
                    );
            })
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->flip()
            ->all();

        $totalOnHand = DecimalMath::zero();
        $totalReserved = DecimalMath::zero();
        $totalAvailable = DecimalMath::zero();
        $totalValuation = DecimalMath::zero();

        $uniqueSkus = [];
        $valuedProductIds = [];
        $missingCostProductIds = [];
        $lowStockItems = [];
        $slowMovingItems = [];
        $lowStockBalanceIds = [];
        $slowMovingBalanceIds = [];
        $outOfStockProductIds = [];
        $lowStockProductIds = [];
        $slowMovingProductIds = [];

        $whBuckets = [];
        foreach ($warehouses as $w) {
            $whBuckets[$w->id] = [
                'warehouse_id' => (int) $w->id,
                'warehouse_name' => (string) $w->name,
                'warehouse_code' => (string) $w->code,
                'branch_name' => (string) ($w->branch?->name ?? '—'),
                'total_on_hand' => DecimalMath::zero(),
                'total_available' => DecimalMath::zero(),
                'low_stock_count' => 0,
            ];
        }

        foreach ($balances as $bal) {
            $product = $bal->product;
            if ($product === null) {
                continue;
            }

            $uniqueSkus[$product->id] = true;
            $onHand = (string) $bal->on_hand;
            $reserved = (string) $bal->reserved;
            $available = (string) $bal->available;
            $reorderPoint = (string) ($product->reorder_point ?? '0.0000');

            $totalOnHand = DecimalMath::add($totalOnHand, $onHand);
            $totalReserved = DecimalMath::add($totalReserved, $reserved);
            $totalAvailable = DecimalMath::add($totalAvailable, $available);

            if (isset($whBuckets[$bal->warehouse_id])) {
                $whBuckets[$bal->warehouse_id]['total_on_hand'] = DecimalMath::add($whBuckets[$bal->warehouse_id]['total_on_hand'], $onHand);
                $whBuckets[$bal->warehouse_id]['total_available'] = DecimalMath::add($whBuckets[$bal->warehouse_id]['total_available'], $available);
            }

            $isOutOfStock = DecimalMath::lte($available, '0');
            $isLowStock = DecimalMath::lte($available, $reorderPoint);

            if ($isOutOfStock) {
                $outOfStockProductIds[$product->id] = true;
            }

            if ($isLowStock) {
                $lowStockBalanceIds[] = (int) $bal->id;
                $lowStockProductIds[$product->id] = true;
                if (isset($whBuckets[$bal->warehouse_id])) {
                    $whBuckets[$bal->warehouse_id]['low_stock_count']++;
                }

                $lowStockItems[] = [
                    'balance_id' => (int) $bal->id,
                    'product_id' => (int) $product->id,
                    'sku' => (string) $product->sku,
                    'product_name' => (string) $product->name,
                    'warehouse_name' => (string) ($bal->warehouse?->name ?? '—'),
                    'warehouse_code' => (string) ($bal->warehouse?->code ?? ''),
                    'available' => $available,
                    'reorder_point' => $reorderPoint,
                    'is_out_of_stock' => $isOutOfStock,
                ];
            }

            if (DecimalMath::gt($onHand, '0') && ! isset($soldProductIds[$product->id])) {
                $slowMovingBalanceIds[] = (int) $bal->id;
                $slowMovingProductIds[$product->id] = true;
                $slowMovingItems[] = [
                    'balance_id' => (int) $bal->id,
                    'product_id' => (int) $product->id,
                    'sku' => (string) $product->sku,
                    'product_name' => (string) $product->name,
                    'warehouse_name' => (string) ($bal->warehouse?->name ?? '—'),
                    'on_hand' => $onHand,
                ];
            }

            // Valuation calculation: only count products with explicit cost_price; track missing costs separately!
            if (DecimalMath::gt($onHand, '0')) {
                if ($product->cost_price !== null) {
                    $valuedProductIds[$product->id] = true;
                    $totalValuation = DecimalMath::add(
                        $totalValuation,
                        DecimalMath::mul($onHand, (string) $product->cost_price)
                    );
                } else {
                    $missingCostProductIds[$product->id] = true;
                }
            }
        }

        // Remove any product from missingCost if another row wasn't missing (safety) and ensure clean counts
        $missingCostCount = count(array_diff_key($missingCostProductIds, $valuedProductIds));

        return [
            'total_skus' => count($uniqueSkus),
            'total_on_hand' => $totalOnHand,
            'total_reserved' => $totalReserved,
            'total_available' => $totalAvailable,
            'low_stock_count' => count($lowStockProductIds),
            'out_of_stock_count' => count($outOfStockProductIds),
            'slow_moving_count' => count($slowMovingProductIds),
            'low_stock_balance_ids' => $lowStockBalanceIds,
            'slow_moving_balance_ids' => $slowMovingBalanceIds,
            'low_stock_items' => $lowStockItems,
            'slow_moving_items' => $slowMovingItems,
            'warehouse_summary' => array_values($whBuckets),
            'valuation' => [
                'total_valuation' => $totalValuation,
                'valued_sku_count' => count($valuedProductIds),
                'missing_cost_sku_count' => $missingCostCount,
                'has_incomplete_valuation' => $missingCostCount > 0,
            ],
        ];
    }
}
