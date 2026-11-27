<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Policies;

use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;

final class PlatformNotificationPolicy
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->tenantContext->id() !== null;
    }

    public function view(User $user, PlatformNotification $notification): bool
    {
        return (int) $notification->tenant_id === $this->tenantContext->id()
            && (int) $notification->user_id === (int) $user->id;
    }

    public function update(User $user, PlatformNotification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
