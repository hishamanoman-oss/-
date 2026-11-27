<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\CarbonImmutable;

final class PerformanceTargetService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Evaluate performance targets within tenant & branch scope using exact bcmath arithmetic.
     *
     * @param  list<int>|null  $allowedBranchIds
     * @return list<array{
     *     id: int,
     *     branch_id: int|null,
     *     branch_name: string,
     *     metric_type: string,
     *     period_type: string,
     *     period_start: string,
     *     period_end: string,
     *     target_value: string,
     *     actual_value: string,
     *     attainment_percent: string,
     *     bar_width_percent: string,
     *     currency_code: string,
     *     status: string
     * }>
     */
    public function evaluateTargets(
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
    ): array {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();

        $query = PerformanceTarget::query()
            ->with('branch:id,name,code')
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->when(
                $branchId !== null,
                fn ($q) => $q->where(fn ($sq) => $sq->where('branch_id', $branchId)->orWhereNull('branch_id')),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->where(fn ($sq) => $sq->whereNull('branch_id')->orWhereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds))
                    : $q
            )
            ->orderByDesc('id');

        $targets = $query->get();
        $results = [];

        foreach ($targets as $target) {
            $start = CarbonImmutable::parse($target->period_start)->startOfDay();
            $end = CarbonImmutable::parse($target->period_end)->endOfDay();

            $ordersQuery = Order::query()
                ->where('order_status', '!=', 'cancelled')
                ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
                ->whereBetween('placed_at', [$start, $end]);

            if ($target->branch_id !== null) {
                $ordersQuery->where('branch_id', $target->branch_id);
            } elseif ($branchId !== null) {
                $ordersQuery->where('branch_id', $branchId);
            } elseif (is_array($allowedBranchIds)) {
                $ordersQuery->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds);
            }

            $orders = $ordersQuery->get(['gross_sales', 'discount_total', 'refunded_total', 'paid_total']);

            $actual = DecimalMath::zero();
            if ($target->metric_type === 'orders_count') {
                $actual = DecimalMath::normalize((string) $orders->count());
            } elseif ($target->metric_type === 'paid_revenue') {
                foreach ($orders as $order) {
                    $actual = DecimalMath::add($actual, (string) $order->paid_total);
                }
            } else {
                foreach ($orders as $order) {
                    $net = DecimalMath::sub(
                        DecimalMath::sub((string) $order->gross_sales, (string) $order->discount_total),
                        (string) $order->refunded_total
                    );
                    $actual = DecimalMath::add($actual, $net);
                }
            }

            $targetValue = DecimalMath::normalize((string) $target->target_value);
            $attainmentPercent = DecimalMath::gt($targetValue, '0')
                ? DecimalMath::mul(DecimalMath::div($actual, $targetValue), '100')
                : DecimalMath::zero();

            $barWidth = DecimalMath::gt($attainmentPercent, '100')
                ? '100.0000'
                : (DecimalMath::lt($attainmentPercent, '0') ? '0.0000' : $attainmentPercent);

            $status = match (true) {
                DecimalMath::gte($attainmentPercent, '100') => 'exceeded',
                DecimalMath::gte($attainmentPercent, '70') => 'on_track',
                default => 'behind',
            };

            $results[] = [
                'id' => (int) $target->id,
                'branch_id' => $target->branch_id ? (int) $target->branch_id : null,
                'branch_name' => $target->branch?->name ?? __('ui.all_branches'),
                'metric_type' => $target->metric_type,
                'period_type' => $target->period_type,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'target_value' => $targetValue,
                'actual_value' => $actual,
                'attainment_percent' => $attainmentPercent,
                'bar_width_percent' => $barWidth,
                'currency_code' => $target->currency_code,
                'status' => $status,
            ];
        }

        return $results;
    }
}
