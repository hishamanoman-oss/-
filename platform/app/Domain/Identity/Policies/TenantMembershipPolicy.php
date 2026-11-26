<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class TenantMembershipPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('team.view');
    }

    public function manage(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('team.manage');
    }

    public function update(User $user, TenantMembership $membership): bool
    {
        if ((int) $membership->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return $this->tenantContext->hasPermission('team.manage');
    }
}
