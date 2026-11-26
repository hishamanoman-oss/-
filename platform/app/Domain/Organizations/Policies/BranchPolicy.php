<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class BranchPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        if ((int) $branch->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('branches.view')
            && $this->tenantContext->canAccessBranch((int) $branch->id);
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('branches.manage')
            && $this->tenantContext->hasFullBranchAccess();
    }

    public function update(User $user, Branch $branch): bool
    {
        if ((int) $branch->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('branches.manage')
            && $this->tenantContext->canAccessBranch((int) $branch->id, requireManage: true);
    }

    public function toggleStatus(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }
}
