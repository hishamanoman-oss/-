<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\FinancialSnapshot;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\CarbonInterface;

final class FinancialMetricsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Summarize separated financial metrics across active (non-cancelled) orders
     * and enforce the Net Profit Completeness Guard.
     *
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     gross_sales: string,
     *     discounts: string,
     *     refunds: string,
     *     net_sales: string,
     *     tax_total: string,
     *     paid_total: string,
     *     outstanding_total: string,
     *     cogs_total: string|null,
     *     gross_profit: string|null,
     *     operating_expenses: string|null,
     *     net_profit: string|null,
     *     profit_completeness_status: string,
     *     orders_count: int,
     *     incomplete_cost_orders_count: int
     * }
     */
    public function summarize(
        ?int $branchId = null,
        ?array $allowedBranchIds = null,
        ?CarbonInterface $snapshotDate = null,
        string|int|null $operatingExpenses = null,
    ): array {
        $query = Order::query()->where('order_status', '!=', 'cancelled');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        } elseif (is_array($allowedBranchIds)) {
            $query->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds);
        }

        if ($snapshotDate !== null) {
            $query->whereDate('placed_at', $snapshotDate->toDateString());
        }

        $orders = $query->get();

        $grossSales = DecimalMath::zero();
        $discounts = DecimalMath::zero();
        $refunds = DecimalMath::zero();
        $taxTotal = DecimalMath::zero();
        $paidTotal = DecimalMath::zero();
        $outstandingTotal = DecimalMath::zero();
        $cogsSum = DecimalMath::zero();
        $incompleteCostCount = 0;

        foreach ($orders as $order) {
            $grossSales = DecimalMath::add($grossSales, (string) $order->gross_sales);
            $discounts = DecimalMath::add($discounts, (string) $order->discount_total);
            $refunds = DecimalMath::add($refunds, (string) $order->refunded_total);
            $taxTotal = DecimalMath::add($taxTotal, (string) $order->tax_total);
            $paidTotal = DecimalMath::add($paidTotal, (string) $order->paid_total);
            $outstandingTotal = DecimalMath::add($outstandingTotal, (string) $order->outstanding_total);

            if (! $order->has_complete_cost_data || $order->cogs_total === null) {
                $incompleteCostCount++;
            } else {
                $cogsSum = DecimalMath::add($cogsSum, (string) $order->cogs_total);
            }
        }

        $netSales = DecimalMath::sub(DecimalMath::sub($grossSales, $discounts), $refunds);
        $hasCompleteCost = $incompleteCostCount === 0;
        $normalizedExpenses = $operatingExpenses !== null ? DecimalMath::normalize($operatingExpenses) : null;

        $cogsTotal = null;
        $grossProfit = null;
        $netProfit = null;

        if (! $hasCompleteCost && $normalizedExpenses === null) {
            $completenessStatus = 'incomplete';
        } elseif (! $hasCompleteCost) {
            $completenessStatus = 'missing_cost';
        } elseif ($normalizedExpenses === null) {
            $cogsTotal = $cogsSum;
            $grossProfit = DecimalMath::sub($netSales, $cogsTotal);
            $completenessStatus = 'missing_expenses';
        } else {
            $cogsTotal = $cogsSum;
            $grossProfit = DecimalMath::sub($netSales, $cogsTotal);
            $netProfit = DecimalMath::sub($grossProfit, $normalizedExpenses);
            $completenessStatus = 'complete';
        }

        return [
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'refunds' => $refunds,
            'net_sales' => $netSales,
            'tax_total' => $taxTotal,
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstandingTotal,
            'cogs_total' => $cogsTotal,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $normalizedExpenses,
            'net_profit' => $netProfit,
            'profit_completeness_status' => $completenessStatus,
            'orders_count' => $orders->count(),
            'incomplete_cost_orders_count' => $incompleteCostCount,
        ];
    }

    public function computeAndStoreSnapshot(
        CarbonInterface $snapshotDate,
        ?int $branchId = null,
        string|int|null $operatingExpenses = null,
        string $currencyCode = 'SAR',
    ): FinancialSnapshot {
        $tenantId = $this->tenantContext->id();
        $summary = $this->summarize(
            branchId: $branchId,
            snapshotDate: $snapshotDate,
            operatingExpenses: $operatingExpenses,
        );

        return FinancialSnapshot::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'snapshot_date' => $snapshotDate->toDateString(),
                'currency_code' => strtoupper($currencyCode),
            ],
            [
                'gross_sales' => $summary['gross_sales'],
                'discounts' => $summary['discounts'],
                'refunds' => $summary['refunds'],
                'net_sales' => $summary['net_sales'],
                'tax_total' => $summary['tax_total'],
                'paid_total' => $summary['paid_total'],
                'outstanding_total' => $summary['outstanding_total'],
                'cogs_total' => $summary['cogs_total'],
                'gross_profit' => $summary['gross_profit'],
                'operating_expenses' => $summary['operating_expenses'],
                'net_profit' => $summary['net_profit'],
                'profit_completeness_status' => $summary['profit_completeness_status'],
            ]
        );
    }
}
