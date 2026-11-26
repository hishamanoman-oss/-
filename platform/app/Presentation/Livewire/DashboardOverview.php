<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class DashboardOverview extends Component
{
    public function render(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->tenant();
        $membership = $tenantContext->membership();

        $branchesQuery = Branch::query();
        $warehousesQuery = Warehouse::query();

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        if (is_array($accessibleBranchIds)) {
            $branchesQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
            $warehousesQuery->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }

        $activeBranchId = $tenantContext->activeBranchId();
        if ($activeBranchId !== null) {
            $warehousesQuery->where('branch_id', $activeBranchId);
        }

        $branchesCount = (clone $branchesQuery)->count();
        $activeBranchesCount = (clone $branchesQuery)->where('status', 'active')->count();
        $warehousesCount = (clone $warehousesQuery)->count();

        $teamCount = $tenant !== null
            ? TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->count()
            : 0;

        $recentAudits = $tenant !== null && $tenantContext->hasPermission('audit.view')
            ? AuditLog::query()
                ->with('actor:id,name,email')
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        $visibleBranches = (clone $branchesQuery)
            ->withCount('warehouses')
            ->orderBy('id')
            ->get();

        return view('livewire.dashboard-overview', [
            'user' => Auth::user(),
            'tenant' => $tenant,
            'membership' => $membership,
            'roles' => $membership?->roles ?? collect(),
            'activeBranch' => $activeBranchId !== null ? Branch::query()->find($activeBranchId) : null,
            'branchesCount' => $branchesCount,
            'activeBranchesCount' => $activeBranchesCount,
            'warehousesCount' => $warehousesCount,
            'teamCount' => $teamCount,
            'visibleBranches' => $visibleBranches,
            'recentAudits' => $recentAudits,
            'deploymentMode' => (string) config('platform.deployment_mode', 'saas'),
        ]);
    }
}
