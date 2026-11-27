<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Analytics\Services\CustomerIntelligenceService;
use App\Domain\Customers\Models\Customer;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

final class CustomersCrmWorkspace extends Component
{
    public string $search = '';

    public bool $showCustomerDrawer = false;

    public ?int $selectedCustomerId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    public function openCustomer(int $customerId): void
    {
        Gate::authorize('viewAny', Order::class);
        Customer::query()->findOrFail($customerId);

        $this->selectedCustomerId = $customerId;
        $this->showCustomerDrawer = true;
    }

    #[On('sync-realtime-refresh')]
    public function refreshFromRealtime(): void
    {
        // Auto-refresh on Reverb sync broadcast
    }

    public function render(TenantContext $tenantContext, CustomerIntelligenceService $customerIntelligence): View
    {
        Gate::authorize('viewAny', Order::class);

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();
        $window = AnalyticsPeriod::resolve('30d');

        $intel = $customerIntelligence->analyze(
            tenantId: $tenantContext->id(),
            allowedBranchIds: $accessibleBranchIds,
            branchId: $activeBranchId,
            period: $window,
            search: $this->search,
        );

        $selectedCustomer = null;
        $selectedOrders = collect();
        $selectedSummary = [
            'lifetime_value' => DecimalMath::zero(),
            'paid_total' => DecimalMath::zero(),
            'outstanding_total' => DecimalMath::zero(),
            'refunded_total' => DecimalMath::zero(),
        ];

        if ($this->showCustomerDrawer && $this->selectedCustomerId !== null) {
            $selectedCustomer = Customer::query()->with('branch:id,name')->find($this->selectedCustomerId);

            if ($selectedCustomer !== null) {
                $ordersQuery = Order::query()
                    ->with('branch:id,name')
                    ->where('customer_id', $selectedCustomer->id);

                if ($activeBranchId !== null) {
                    $ordersQuery->where('branch_id', $activeBranchId);
                } elseif (is_array($accessibleBranchIds)) {
                    $ordersQuery->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
                }

                $selectedOrders = $ordersQuery->latest('placed_at')->latest('id')->limit(20)->get();

                foreach ($selectedOrders as $ord) {
                    if ($ord->order_status === 'cancelled') {
                        continue;
                    }
                    $net = DecimalMath::sub(
                        DecimalMath::sub((string) $ord->gross_sales, (string) $ord->discount_total),
                        (string) $ord->refunded_total
                    );
                    $selectedSummary['lifetime_value'] = DecimalMath::add($selectedSummary['lifetime_value'], $net);
                    $selectedSummary['paid_total'] = DecimalMath::add($selectedSummary['paid_total'], (string) $ord->paid_total);
                    $selectedSummary['outstanding_total'] = DecimalMath::add($selectedSummary['outstanding_total'], (string) $ord->outstanding_total);
                    $selectedSummary['refunded_total'] = DecimalMath::add($selectedSummary['refunded_total'], (string) $ord->refunded_total);
                }
            }
        }

        return view('livewire.customers-crm-workspace', [
            'intel' => $intel,
            'currency' => $tenantContext->tenant()?->default_currency ?? 'SAR',
            'selectedCustomer' => $selectedCustomer,
            'selectedOrders' => $selectedOrders,
            'selectedSummary' => $selectedSummary,
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
