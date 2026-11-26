<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Policies;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class DataSourcePolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null
            && $this->tenantContext->hasPermission('integrations.view');
    }

    public function view(User $user, DataSource $dataSource): bool
    {
        return (int) $dataSource->tenant_id === $this->tenantContext->id()
            && $this->tenantContext->hasPermission('integrations.view');
    }

    public function manage(User $user, ?DataSource $dataSource = null): bool
    {
        if ($this->tenantContext->id() === null || ! $this->tenantContext->hasPermission('integrations.manage')) {
            return false;
        }

        if ($dataSource !== null && (int) $dataSource->tenant_id !== $this->tenantContext->id()) {
            return false;
        }

        return true;
    }
}
