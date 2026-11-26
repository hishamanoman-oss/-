<?php

declare(strict_types=1);

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\Order;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class OrderPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('sales.view');
    }

    public function view(User $user, Order $order): bool
    {
        if ((int) $order->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('sales.view')
            && $this->tenantContext->canAccessBranch((int) $order->branch_id);
    }
}
