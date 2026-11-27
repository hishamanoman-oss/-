<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Policies;

use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class NotificationRulePolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasAnyPermission(['dashboard.view', 'reports.view', 'settings.manage']);
    }

    public function manage(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && ! $this->tenantContext->hasRole('observer')
            && $this->tenantContext->hasAnyPermission(['settings.manage', 'branches.manage']);
    }

    public function update(User $user, NotificationRule $rule): bool
    {
        if ((int) $rule->tenant_id !== $this->tenantContext->id() || ! $this->manage($user)) {
            return false;
        }

        return $rule->branch_id === null || $this->tenantContext->canAccessBranch((int) $rule->branch_id);
    }
}
