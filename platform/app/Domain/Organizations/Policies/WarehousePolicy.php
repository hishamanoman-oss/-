<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class WarehousePolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function createForBranch(User $user, Branch $branch): bool
    {
        if ((int) $branch->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('warehouses.manage')
            && $this->tenantContext->canAccessBranch((int) $branch->id, requireManage: true);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        if ((int) $warehouse->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('warehouses.manage')
            && $this->tenantContext->canAccessBranch((int) $warehouse->branch_id, requireManage: true);
    }

    public function adjust(User $user, Warehouse $warehouse): bool
    {
        if ((int) $warehouse->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('inventory.adjust')
            && $this->tenantContext->canAccessBranch((int) $warehouse->branch_id, requireManage: true);
    }
}
