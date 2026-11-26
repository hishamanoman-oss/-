<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

final class OrdersWorkspace extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showOrderDrawer = false;

    public ?int $selectedOrderId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Order::class);
    }

    #[On('sync-realtime-refresh')]
    public function refreshFromRealtime(): void
    {
        // Trigger Livewire re-render cleanly when Reverb pushes SyncBatchCompleted
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openOrder(int $orderId): void
    {
        $order = Order::query()->findOrFail($orderId);
        Gate::authorize('view', $order);

        $this->selectedOrderId = $order->id;
        $this->showOrderDrawer = true;
    }

    public function render(TenantContext $tenantContext, FinancialMetricsService $metricsService): View
    {
        Gate::authorize('viewAny', Order::class);

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        $ordersQuery = Order::query()->with(['branch', 'customer']);

        if ($activeBranchId !== null) {
            $ordersQuery->where('branch_id', $activeBranchId);
        } elseif (is_array($accessibleBranchIds)) {
            $ordersQuery->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }

        if ($this->statusFilter !== 'all') {
            $ordersQuery->where('order_status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $ordersQuery->where(function ($q) use ($term): void {
                $q->where('order_number', 'like', $term)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', $term));
            });
        }

        $selectedOrder = null;
        if ($this->showOrderDrawer && $this->selectedOrderId !== null) {
            $candidate = Order::query()
                ->with(['branch', 'warehouse', 'customer', 'items', 'payments', 'refunds'])
                ->find($this->selectedOrderId);

            if ($candidate !== null && Gate::allows('view', $candidate)) {
                $selectedOrder = $candidate;
            }
        }

        $financialSummary = $metricsService->summarize(
            branchId: $activeBranchId,
            allowedBranchIds: $accessibleBranchIds,
        );

        return view('livewire.orders-workspace', [
            'orders' => $ordersQuery->latest('placed_at')->latest('id')->paginate(12),
            'selectedOrder' => $selectedOrder,
            'summary' => $financialSummary,
            'currency' => $tenantContext->tenant()?->default_currency ?? 'SAR',
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
