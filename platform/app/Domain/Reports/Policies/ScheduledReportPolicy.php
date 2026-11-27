<?php

declare(strict_types=1);

namespace App\Domain\Reports\Policies;

use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class ScheduledReportPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('reports.view');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ScheduledReport $report): bool
    {
        if ((int) $report->tenant_id !== $this->tenantContext->id() || ! $this->viewAny($user)) {
            return false;
        }

        return $report->branch_id === null || $this->tenantContext->canAccessBranch((int) $report->branch_id);
    }

    public function update(User $user, ScheduledReport $report): bool
    {
        return $this->view($user, $report);
    }
}
