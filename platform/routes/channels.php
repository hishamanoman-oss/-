<?php

use App\Domain\Identity\Models\TenantMembership;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    if (! $user || ! $user->is_active) {
        return false;
    }

    return TenantMembership::query()
        ->where('user_id', $user->id)
        ->where('tenant_id', (int) $tenantId)
        ->where('status', 'active')
        ->exists();
});
