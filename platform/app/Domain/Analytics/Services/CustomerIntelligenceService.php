<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Customers\Models\Customer;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;

final class CustomerIntelligenceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     total_customers: int,
     *     active_customers_count: int,
     *     repeat_customers_count: int,
     *     total_outstanding_receivables: string,
     *     customers: list<array{
     *         customer_id: int,
     *         code: string,
     *         name: string,
     *         phone: string|null,
     *         email: string|null,
     *         branch_name: string|null,
     *         orders_count: int,
     *         is_repeat_buyer: bool,
     *         ordered_in_period: bool,
     *         lifetime_value: string,
     *         paid_total: string,
     *         outstanding_total: string,
     *         last_order_at: string|null,
     *         external_id: string|null
     *     }>
     * }
     */
    public function analyze(
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
        ?AnalyticsPeriod $period = null,
        string $search = '',
    ): array {
        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        $window = $period ?? AnalyticsPeriod::resolve('30d');

        // Permitted non-cancelled orders grouped per customer
        $orders = Order::query()
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->where('order_status', '!=', 'cancelled')
            ->whereNotNull('customer_id')
            ->when(
                $branchId !== null,
                fn ($q) => $q->where('branch_id', $branchId),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->whereIn('branch_id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                    : $q
            )
            ->get();

        $statsByCustomer = [];
        foreach ($orders as $order) {
            $cId = (int) $order->customer_id;
            if (! isset($statsByCustomer[$cId])) {
                $statsByCustomer[$cId] = [
                    'orders_count' => 0,
                    'ordered_in_period' => false,
                    'lifetime_value' => DecimalMath::zero(),
                    'paid_total' => DecimalMath::zero(),
                    'outstanding_total' => DecimalMath::zero(),
                    'last_order_ts' => null,
                ];
            }

            $net = DecimalMath::sub(
                DecimalMath::sub((string) $order->gross_sales, (string) $order->discount_total),
                (string) $order->refunded_total
            );

            $statsByCustomer[$cId]['orders_count']++;
            $statsByCustomer[$cId]['lifetime_value'] = DecimalMath::add($statsByCustomer[$cId]['lifetime_value'], $net);
            $statsByCustomer[$cId]['paid_total'] = DecimalMath::add($statsByCustomer[$cId]['paid_total'], (string) $order->paid_total);
            $statsByCustomer[$cId]['outstanding_total'] = DecimalMath::add($statsByCustomer[$cId]['outstanding_total'], (string) $order->outstanding_total);

            if ($order->placed_at !== null) {
                if ($order->placed_at->betweenIncluded($window->start, $window->end)) {
                    $statsByCustomer[$cId]['ordered_in_period'] = true;
                }

                if ($statsByCustomer[$cId]['last_order_ts'] === null || $order->placed_at->gt($statsByCustomer[$cId]['last_order_ts'])) {
                    $statsByCustomer[$cId]['last_order_ts'] = $order->placed_at;
                }
            }
        }

        $orderedCustomerIds = array_keys($statsByCustomer);
        $isBranchScoped = $branchId !== null || is_array($allowedBranchIds);

        $customersQuery = Customer::query()
            ->with('branch:id,name')
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->when($isBranchScoped, function ($q) use ($branchId, $allowedBranchIds, $orderedCustomerIds): void {
                $scopedBranches = $branchId !== null ? [$branchId] : ($allowedBranchIds ?? []);
                $q->where(function ($sub) use ($scopedBranches, $orderedCustomerIds): void {
                    $sub->whereIn('branch_id', $scopedBranches === [] ? [-1] : $scopedBranches);
                    if ($orderedCustomerIds !== []) {
                        $sub->orWhereIn('id', $orderedCustomerIds);
                    }
                });
            });

        if (trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $customersQuery->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $customers = $customersQuery->orderBy('id')->get();

        $extMap = ExternalMapping::query()
            ->when($resolvedTenantId !== null, fn ($q) => $q->where('tenant_id', $resolvedTenantId))
            ->where('canonical_entity', 'customer')
            ->whereIn('internal_id', $customers->pluck('id')->all() ?: [-1])
            ->pluck('external_id', 'internal_id')
            ->all();

        $rows = [];
        $activeCount = 0;
        $repeatCount = 0;
        $totalReceivables = DecimalMath::zero();

        foreach ($customers as $customer) {
            $st = $statsByCustomer[$customer->id] ?? [
                'orders_count' => 0,
                'ordered_in_period' => false,
                'lifetime_value' => DecimalMath::zero(),
                'paid_total' => DecimalMath::zero(),
                'outstanding_total' => DecimalMath::zero(),
                'last_order_ts' => null,
            ];

            $isRepeat = $st['orders_count'] >= 2;
            if ($st['ordered_in_period']) {
                $activeCount++;
            }
            if ($isRepeat) {
                $repeatCount++;
            }
            $totalReceivables = DecimalMath::add($totalReceivables, $st['outstanding_total']);

            $rows[] = [
                'customer_id' => (int) $customer->id,
                'code' => (string) $customer->code,
                'name' => (string) $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'branch_name' => $customer->branch?->name,
                'orders_count' => $st['orders_count'],
                'is_repeat_buyer' => $isRepeat,
                'ordered_in_period' => $st['ordered_in_period'],
                'lifetime_value' => $st['lifetime_value'],
                'paid_total' => $st['paid_total'],
                'outstanding_total' => $st['outstanding_total'],
                'last_order_at' => $st['last_order_ts']?->format('Y-m-d H:i'),
                'external_id' => isset($extMap[$customer->id]) ? (string) $extMap[$customer->id] : null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => DecimalMath::comp($b['lifetime_value'], $a['lifetime_value']));

        return [
            'total_customers' => count($rows),
            'active_customers_count' => $activeCount,
            'repeat_customers_count' => $repeatCount,
            'total_outstanding_receivables' => $totalReceivables,
            'customers' => $rows,
        ];
    }
}
