<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\CarbonImmutable;

final class SalesAnalyticsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     period: AnalyticsPeriod,
     *     gross_sales: string,
     *     discounts: string,
     *     refunds: string,
     *     net_sales: string,
     *     tax_total: string,
     *     paid_total: string,
     *     outstanding_total: string,
     *     orders_count: int,
     *     average_order_value: string,
     *     cancelled_orders_count: int,
     *     delayed_orders_count: int,
     *     previous_net_sales: string,
     *     previous_orders_count: int,
     *     delta_amount: string,
     *     delta_percent: string|null,
     *     top_branches: list<array{branch_id: int, branch_name: string, branch_code: string, orders_count: int, net_sales: string, paid_total: string}>,
     *     top_products: list<array{product_id: int|null, sku: string, name: string, quantity_sold: string, revenue_total: string}>,
     *     delayed_orders: list<array{id: int, order_number: string, branch_name: string, placed_at: string, net_sales: string}>
     * }
     */
    public function analyze(
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
        ?AnalyticsPeriod $period = null,
        int $delayThresholdHours = 24,
        int $topLimit = 5,
    ): array {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        $window = $period ?? AnalyticsPeriod::resolve('30d');

        $baseScope = Order::query()
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->when(
                $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                    : $q
            );

        $currentOrders = (clone $baseScope)
            ->with('branch:id,name,code')
            ->whereBetween('placed_at', [$window->start, $window->end])
            ->get();

        $previousOrders = (clone $baseScope)
            ->where('order_status', '!=', 'cancelled')
            ->whereBetween('placed_at', [$window->previousStart, $window->previousEnd])
            ->get();

        $grossSales = DecimalMath::zero();
        $discounts = DecimalMath::zero();
        $refunds = DecimalMath::zero();
        $taxTotal = DecimalMath::zero();
        $paidTotal = DecimalMath::zero();
        $outstandingTotal = DecimalMath::zero();
        $activeOrdersCount = 0;
        $cancelledCount = 0;

        $delayCutoff = CarbonImmutable::now()->subHours($delayThresholdHours);
        $delayedOrders = [];
        $branchBuckets = [];
        $activeOrderIds = [];

        foreach ($currentOrders as $order) {
            if ($order->order_status === 'cancelled') {
                $cancelledCount++;

                continue;
            }

            $activeOrdersCount++;
            $activeOrderIds[] = $order->id;

            $orderGross = (string) $order->gross_sales;
            $orderDisc = (string) $order->discount_total;
            $orderRefund = (string) $order->refunded_total;
            $orderTax = (string) $order->tax_total;
            $orderPaid = (string) $order->paid_total;
            $orderOutstanding = (string) $order->outstanding_total;
            $orderNet = DecimalMath::sub(DecimalMath::sub($orderGross, $orderDisc), $orderRefund);

            $grossSales = DecimalMath::add($grossSales, $orderGross);
            $discounts = DecimalMath::add($discounts, $orderDisc);
            $refunds = DecimalMath::add($refunds, $orderRefund);
            $taxTotal = DecimalMath::add($taxTotal, $orderTax);
            $paidTotal = DecimalMath::add($paidTotal, $orderPaid);
            $outstandingTotal = DecimalMath::add($outstandingTotal, $orderOutstanding);

            if (
                ! in_array($order->fulfillment_status, ['fulfilled', 'returned'], true)
                && $order->placed_at !== null
                && $order->placed_at->lte($delayCutoff)
            ) {
                $delayedOrders[] = [
                    'id' => (int) $order->id,
                    'order_number' => (string) $order->order_number,
                    'branch_name' => (string) ($order->branch?->name ?? '—'),
                    'placed_at' => $order->placed_at->format('Y-m-d H:i'),
                    'net_sales' => $orderNet,
                ];
            }

            $bId = (int) $order->branch_id;
            if (! isset($branchBuckets[$bId])) {
                $branchBuckets[$bId] = [
                    'branch_id' => $bId,
                    'branch_name' => (string) ($order->branch?->name ?? "#{$bId}"),
                    'branch_code' => (string) ($order->branch?->code ?? ''),
                    'orders_count' => 0,
                    'net_sales' => DecimalMath::zero(),
                    'paid_total' => DecimalMath::zero(),
                ];
            }
            $branchBuckets[$bId]['orders_count']++;
            $branchBuckets[$bId]['net_sales'] = DecimalMath::add($branchBuckets[$bId]['net_sales'], $orderNet);
            $branchBuckets[$bId]['paid_total'] = DecimalMath::add($branchBuckets[$bId]['paid_total'], $orderPaid);
        }

        $netSales = DecimalMath::sub(DecimalMath::sub($grossSales, $discounts), $refunds);
        $averageOrderValue = $activeOrdersCount > 0
            ? DecimalMath::div($netSales, $activeOrdersCount)
            : DecimalMath::zero();

        $prevNetSales = DecimalMath::zero();
        foreach ($previousOrders as $pOrder) {
            $pNet = DecimalMath::sub(
                DecimalMath::sub((string) $pOrder->gross_sales, (string) $pOrder->discount_total),
                (string) $pOrder->refunded_total
            );
            $prevNetSales = DecimalMath::add($prevNetSales, $pNet);
        }

        $deltaAmount = DecimalMath::sub($netSales, $prevNetSales);
        $deltaPercent = DecimalMath::gt($prevNetSales, '0')
            ? DecimalMath::mul(DecimalMath::div($deltaAmount, $prevNetSales), '100')
            : null;

        usort($branchBuckets, fn (array $a, array $b): int => DecimalMath::comp($b['net_sales'], $a['net_sales']));
        $topBranches = array_slice(array_values($branchBuckets), 0, $topLimit);

        // Top Selling Products within permitted active orders
        $productBuckets = [];
        if ($activeOrderIds !== []) {
            $items = OrderItem::query()
                ->whereIn('order_id', $activeOrderIds)
                ->get();

            foreach ($items as $item) {
                $key = $item->product_id ? "p:{$item->product_id}" : "sku:{$item->sku_snapshot}";
                if (! isset($productBuckets[$key])) {
                    $productBuckets[$key] = [
                        'product_id' => $item->product_id ? (int) $item->product_id : null,
                        'sku' => (string) $item->sku_snapshot,
                        'name' => (string) $item->product_name_snapshot,
                        'quantity_sold' => DecimalMath::zero(),
                        'revenue_total' => DecimalMath::zero(),
                    ];
                }
                $productBuckets[$key]['quantity_sold'] = DecimalMath::add(
                    $productBuckets[$key]['quantity_sold'],
                    (string) $item->quantity
                );
                $productBuckets[$key]['revenue_total'] = DecimalMath::add(
                    $productBuckets[$key]['revenue_total'],
                    (string) $item->line_total
                );
            }
        }

        usort($productBuckets, fn (array $a, array $b): int => DecimalMath::comp($b['revenue_total'], $a['revenue_total']));
        $topProducts = array_slice(array_values($productBuckets), 0, $topLimit);

        return [
            'period' => $window,
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'refunds' => $refunds,
            'net_sales' => $netSales,
            'tax_total' => $taxTotal,
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstandingTotal,
            'orders_count' => $activeOrdersCount,
            'average_order_value' => $averageOrderValue,
            'cancelled_orders_count' => $cancelledCount,
            'delayed_orders_count' => count($delayedOrders),
            'previous_net_sales' => $prevNetSales,
            'previous_orders_count' => $previousOrders->count(),
            'delta_amount' => $deltaAmount,
            'delta_percent' => $deltaPercent,
            'top_branches' => $topBranches,
            'top_products' => $topProducts,
            'delayed_orders' => array_slice($delayedOrders, 0, $topLimit),
        ];
    }

    /**
     * Build day-by-day Net Sales and Paid Revenue time series comparing current period vs previous period.
     *
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     labels: list<string>,
     *     current_net_sales: list<float>,
     *     previous_net_sales: list<float>,
     *     current_paid_revenue: list<float>
     * }
     */
    public function dailyTrend(
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
        ?AnalyticsPeriod $period = null,
    ): array {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        $window = $period ?? AnalyticsPeriod::resolve('30d');

        $daysCount = max(1, min(90, (int) $window->start->startOfDay()->diffInDays($window->end->startOfDay()) + 1));
        $startDay = $window->start->startOfDay();
        $prevStartDay = $window->previousStart->startOfDay();

        $labels = [];
        $currentBuckets = [];
        $paidBuckets = [];
        $prevBuckets = [];

        for ($i = 0; $i < $daysCount; $i++) {
            $dayKey = $startDay->addDays($i)->toDateString();
            $labels[] = $dayKey;
            $currentBuckets[$dayKey] = DecimalMath::zero();
            $paidBuckets[$dayKey] = DecimalMath::zero();
            $prevBuckets[$prevStartDay->addDays($i)->toDateString()] = DecimalMath::zero();
        }

        $baseScope = Order::query()
            ->where('order_status', '!=', 'cancelled')
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->when(
                $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                    : $q
            );

        $currentOrders = (clone $baseScope)
            ->whereBetween('placed_at', [$window->start, $window->end])
            ->get(['placed_at', 'gross_sales', 'discount_total', 'refunded_total', 'paid_total']);

        foreach ($currentOrders as $order) {
            if ($order->placed_at === null) {
                continue;
            }
            $dayKey = $order->placed_at->toDateString();
            if (! isset($currentBuckets[$dayKey])) {
                continue;
            }
            $orderNet = DecimalMath::sub(
                DecimalMath::sub((string) $order->gross_sales, (string) $order->discount_total),
                (string) $order->refunded_total
            );
            $currentBuckets[$dayKey] = DecimalMath::add($currentBuckets[$dayKey], $orderNet);
            $paidBuckets[$dayKey] = DecimalMath::add($paidBuckets[$dayKey], (string) $order->paid_total);
        }

        $previousOrders = (clone $baseScope)
            ->whereBetween('placed_at', [$window->previousStart, $window->previousEnd])
            ->get(['placed_at', 'gross_sales', 'discount_total', 'refunded_total']);

        foreach ($previousOrders as $pOrder) {
            if ($pOrder->placed_at === null) {
                continue;
            }
            $dayKey = $pOrder->placed_at->toDateString();
            if (! isset($prevBuckets[$dayKey])) {
                continue;
            }
            $orderNet = DecimalMath::sub(
                DecimalMath::sub((string) $pOrder->gross_sales, (string) $pOrder->discount_total),
                (string) $pOrder->refunded_total
            );
            $prevBuckets[$dayKey] = DecimalMath::add($prevBuckets[$dayKey], $orderNet);
        }

        $currentSeries = [];
        $previousSeries = [];
        $paidSeries = [];
        for ($i = 0; $i < $daysCount; $i++) {
            $curKey = $startDay->addDays($i)->toDateString();
            $prevKey = $prevStartDay->addDays($i)->toDateString();
            $currentSeries[] = round((float) $currentBuckets[$curKey], 2);
            $paidSeries[] = round((float) $paidBuckets[$curKey], 2);
            $previousSeries[] = round((float) ($prevBuckets[$prevKey] ?? '0'), 2);
        }

        return [
            'labels' => $labels,
            'current_net_sales' => $currentSeries,
            'previous_net_sales' => $previousSeries,
            'current_paid_revenue' => $paidSeries,
        ];
    }
}
