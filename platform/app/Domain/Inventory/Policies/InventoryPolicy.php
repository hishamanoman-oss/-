<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class InventoryPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('inventory.view');
    }

    public function viewBalance(User $user, InventoryBalance $balance): bool
    {
        if ((int) $balance->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('inventory.view')
            && $this->tenantContext->canAccessBranch((int) $balance->warehouse->branch_id);
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
