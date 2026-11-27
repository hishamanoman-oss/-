<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Analytics\Services\CustomerIntelligenceService;
use App\Domain\Analytics\Services\InventoryIntelligenceService;
use App\Domain\Analytics\Services\SalesAnalyticsService;
use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Synchronization\Services\FreshnessEvaluator;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

final class BusinessDashboardWorkspace extends Component
{
    public string $period = '30d';

    public string $customFrom = '';

    public string $customTo = '';

    public function mount(): void
    {
        $this->customFrom = now()->subDays(29)->toDateString();
        $this->customTo = now()->toDateString();
    }

    #[On('sync-realtime-refresh')]
    #[On('sync-freshness-refresh')]
    public function refreshFromRealtime(): void
    {
        // Trigger clean Livewire re-render when Reverb pushes SyncBatchCompleted
    }

    public function render(
        TenantContext $tenantContext,
        SalesAnalyticsService $salesAnalytics,
        InventoryIntelligenceService $inventoryIntelligence,
        CustomerIntelligenceService $customerIntelligence,
        FinancialMetricsService $financialMetrics,
        FreshnessEvaluator $freshnessEvaluator,
    ): View {
        $tenant = $tenantContext->tenant();
        $membership = $tenantContext->membership();
        $roles = $tenantContext->roleSlugs();
        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        $window = AnalyticsPeriod::resolve(
            key: $this->period,
            customFrom: $this->customFrom !== '' ? $this->customFrom : null,
            customTo: $this->customTo !== '' ? $this->customTo : null,
        );

        $sales = $salesAnalytics->analyze(
            tenantId: $tenant?->id,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $activeBranchId,
            period: $window,
        );

        $inventory = $inventoryIntelligence->analyze(
            tenantId: $tenant?->id,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $activeBranchId,
            period: $window,
        );

        $customers = $customerIntelligence->analyze(
            tenantId: $tenant?->id,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $activeBranchId,
            period: $window,
        );

        $financials = $financialMetrics->summarize(
            branchId: $activeBranchId,
            allowedBranchIds: $accessibleBranchIds,
            from: $window->start,
            to: $window->end,
        );

        // Authorized branches list (merged with sales ranking so every permitted branch renders visibly)
        $branchesQuery = Branch::query()->withCount('warehouses')->orderBy('id');
        if ($activeBranchId !== null) {
            $branchesQuery->where('id', $activeBranchId);
        } elseif (is_array($accessibleBranchIds)) {
            $branchesQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        $permittedBranches = $branchesQuery->get();

        $salesByBranchId = [];
        foreach ($sales['top_branches'] as $tb) {
            $salesByBranchId[$tb['branch_id']] = $tb;
        }

        $branchPerformance = $permittedBranches->map(function (Branch $branch) use ($salesByBranchId): array {
            $stat = $salesByBranchId[$branch->id] ?? null;

            return [
                'branch_id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'status' => $branch->status,
                'warehouses_count' => $branch->warehouses_count,
                'orders_count' => $stat['orders_count'] ?? 0,
                'net_sales' => $stat['net_sales'] ?? DecimalMath::zero(),
                'paid_total' => $stat['paid_total'] ?? DecimalMath::zero(),
            ];
        })->sort(fn (array $a, array $b): int => DecimalMath::comp($b['net_sales'], $a['net_sales']))->values();

        // Sync freshness issues for Attention Needed panel
        $syncAlerts = [];
        if ($tenant !== null && $tenantContext->hasPermission('integrations.view')) {
            $sources = DataSource::query()->whereIn('status', ['active', 'error'])->get();
            foreach ($sources as $src) {
                $status = $freshnessEvaluator->evaluate($src);
                if (in_array($status, ['error', 'delayed', 'disconnected'], true)) {
                    $syncAlerts[] = [
                        'id' => $src->id,
                        'name' => $src->name,
                        'freshness' => $status,
                        'error' => $src->last_error_summary,
                    ];
                }
            }
        }

        // Determine primary role focus
        $focusRole = match (true) {
            in_array('tenant_owner', $roles, true) || in_array('general_manager', $roles, true) => 'executive',
            in_array('accountant', $roles, true) => 'accountant',
            in_array('inventory_manager', $roles, true) => 'inventory',
            in_array('branch_manager', $roles, true) => 'branch',
            default => 'observer',
        };

        return view('livewire.business-dashboard-workspace', [
            'user' => Auth::user(),
            'tenant' => $tenant,
            'roles' => $membership?->roles ?? collect(),
            'focusRole' => $focusRole,
            'activeBranch' => $activeBranchId !== null ? Branch::query()->find($activeBranchId) : null,
            'branchesCount' => $permittedBranches->count(),
            'activeBranchesCount' => $permittedBranches->where('status', 'active')->count(),
            'branchPerformance' => $branchPerformance,
            'sales' => $sales,
            'inventory' => $inventory,
            'customers' => $customers,
            'financials' => $financials,
            'syncAlerts' => $syncAlerts,
            'currency' => $tenant?->default_currency ?? 'SAR',
            'decimalHelper' => DecimalMath::class,
            'canViewSales' => $tenantContext->hasPermission('sales.view'),
            'canViewInventory' => $tenantContext->hasPermission('inventory.view'),
            'canViewFinancials' => $tenantContext->hasAnyPermission(['reports.view', 'sales.view']),
            'canViewIntegrations' => $tenantContext->hasPermission('integrations.view'),
            'deploymentMode' => (string) config('platform.deployment_mode', 'saas'),
        ]);
    }
}
