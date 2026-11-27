<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Policies;

use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class PerformanceTargetPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasAnyPermission(['reports.view', 'dashboard.view']);
    }

    public function manage(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && ! $this->tenantContext->hasRole('observer')
            && $this->tenantContext->hasAnyPermission(['settings.manage', 'branches.manage']);
    }

    public function update(User $user, PerformanceTarget $target): bool
    {
        if ((int) $target->tenant_id !== $this->tenantContext->id() || ! $this->manage($user)) {
            return false;
        }

        return $target->branch_id === null || $this->tenantContext->canAccessBranch((int) $target->branch_id);
    }
}
