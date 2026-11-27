<?php

declare(strict_types=1);

namespace App\Domain\Commands\Policies;

use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class RemoteCommandPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasAnyPermission(['commands.request', 'commands.approve', 'audit.view']);
    }

    public function view(User $user, RemoteCommand $command): bool
    {
        if ((int) $command->tenant_id !== $this->tenantContext->id() || ! $this->viewAny($user)) {
            return false;
        }

        if ($command->branch_id !== null) {
            return $this->tenantContext->canAccessBranch((int) $command->branch_id);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('commands.request');
    }

    public function approve(User $user, RemoteCommand $command): bool
    {
        if ((int) $command->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        if (! $this->tenantContext->hasPermission('commands.approve')) {
            return false;
        }

        if ($command->branch_id !== null) {
            return $this->tenantContext->canAccessBranch((int) $command->branch_id);
        }

        return true;
    }
}
