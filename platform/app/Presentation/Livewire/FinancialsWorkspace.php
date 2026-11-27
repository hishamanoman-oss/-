<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Orders\Models\Payment;
use App\Domain\Orders\Models\Refund;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class FinancialsWorkspace extends Component
{
    public string $period = '30d';

    public string $customFrom = '';

    public string $customTo = '';

    public function mount(TenantContext $tenantContext): void
    {
        if (! $tenantContext->hasAnyPermission(['reports.view', 'sales.view'])) {
            abort(403, 'Missing financial reports or sales view permission.');
        }

        $this->customFrom = now()->subDays(29)->toDateString();
        $this->customTo = now()->toDateString();
    }

    #[On('sync-realtime-refresh')]
    public function refreshFromRealtime(): void
    {
        // Auto-refresh on Reverb sync broadcast
    }

    public function render(TenantContext $tenantContext, FinancialMetricsService $metricsService): View
    {
        if (! $tenantContext->hasAnyPermission(['reports.view', 'sales.view'])) {
            abort(403, 'Missing financial reports or sales view permission.');
        }

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        $window = AnalyticsPeriod::resolve(
            key: $this->period,
            customFrom: $this->customFrom !== '' ? $this->customFrom : null,
            customTo: $this->customTo !== '' ? $this->customTo : null,
        );

        $summary = $metricsService->summarize(
            branchId: $activeBranchId,
            allowedBranchIds: $accessibleBranchIds,
            from: $window->start,
            to: $window->end,
        );

        $branchBreakdown = $metricsService->summarizeByBranch(
            branchId: $activeBranchId,
            allowedBranchIds: $accessibleBranchIds,
            from: $window->start,
            to: $window->end,
        );

        $payments = Payment::query()
            ->with(['order.branch:id,name'])
            ->whereHas('order', function ($q) use ($activeBranchId, $accessibleBranchIds, $window): void {
                $q->where('order_status', '!=', 'cancelled')
                    ->whereBetween('placed_at', [$window->start, $window->end])
                    ->when(
                        $activeBranchId !== null,
                        fn ($oq) => $oq->where('branch_id', $activeBranchId),
                        fn ($oq) => is_array($accessibleBranchIds)
                            ? $oq->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds)
                            : $oq
                    );
            })
            ->latest('paid_at')
            ->latest('id')
            ->limit(25)
            ->get();

        $refunds = Refund::query()
            ->with(['order.branch:id,name'])
            ->whereHas('order', function ($q) use ($activeBranchId, $accessibleBranchIds, $window): void {
                $q->whereBetween('placed_at', [$window->start, $window->end])
                    ->when(
                        $activeBranchId !== null,
                        fn ($oq) => $oq->where('branch_id', $activeBranchId),
                        fn ($oq) => is_array($accessibleBranchIds)
                            ? $oq->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds)
                            : $oq
                    );
            })
            ->latest('refunded_at')
            ->latest('id')
            ->limit(25)
            ->get();

        return view('livewire.financials-workspace', [
            'summary' => $summary,
            'branchBreakdown' => $branchBreakdown,
            'payments' => $payments,
            'refunds' => $refunds,
            'currency' => $tenantContext->tenant()?->default_currency ?? 'SAR',
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
