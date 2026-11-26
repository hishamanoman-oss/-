<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Services;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Tenants\Models\Tenant;
use Illuminate\Support\Collection;

final class TenantContext
{
    private ?int $tenantId = null;

    private ?Tenant $tenant = null;

    private ?TenantMembership $membership = null;

    private ?int $activeBranchId = null;

    private int $bypassDepth = 0;

    /** @var list<string>|null */
    private ?array $cachedPermissionKeys = null;

    /** @var list<string>|null */
    private ?array $cachedRoleSlugs = null;

    public function bind(Tenant $tenant, ?TenantMembership $membership = null): void
    {
        $this->tenantId = (int) $tenant->id;
        $this->tenant = $tenant;
        $this->membership = $membership;
        $this->cachedPermissionKeys = null;
        $this->cachedRoleSlugs = null;
    }

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = null;
        $this->membership = null;
        $this->cachedPermissionKeys = null;
        $this->cachedRoleSlugs = null;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function tenant(): ?Tenant
    {
        if ($this->tenant === null && $this->tenantId !== null) {
            $this->tenant = Tenant::query()->find($this->tenantId);
        }

        return $this->tenant;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function setActiveBranchId(?int $branchId): void
    {
        $this->activeBranchId = $branchId;
    }

    public function activeBranchId(): ?int
    {
        return $this->activeBranchId;
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Execute a callback without tenant global scoping (for Platform Admin, Auth, or Seeders).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runWithoutTenantScope(callable $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth = max(0, $this->bypassDepth - 1);
        }
    }

    /**
     * @return list<string>
     */
    public function roleSlugs(): array
    {
        if ($this->cachedRoleSlugs !== null) {
            return $this->cachedRoleSlugs;
        }

        if ($this->membership === null) {
            return $this->cachedRoleSlugs = [];
        }

        $this->cachedRoleSlugs = $this->membership->roles()
            ->pluck('slug')
            ->map(fn (mixed $slug): string => (string) $slug)
            ->values()
            ->all();

        return $this->cachedRoleSlugs;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->cachedPermissionKeys !== null) {
            return $this->cachedPermissionKeys;
        }

        if ($this->membership === null) {
            return $this->cachedPermissionKeys = [];
        }

        $this->cachedPermissionKeys = $this->membership->roles()
            ->with('permissions:id,key')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('key'))
            ->unique()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();

        return $this->cachedPermissionKeys;
    }

    public function hasPermission(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissionKeys(), true);
    }

    public function hasFullBranchAccess(): bool
    {
        $roles = $this->roleSlugs();

        return in_array('tenant_owner', $roles, true)
            || in_array('general_manager', $roles, true);
    }

    /**
     * Null means unrestricted access across all branches of the active tenant.
     *
     * @return list<int>|null
     */
    public function accessibleBranchIds(): ?array
    {
        if ($this->membership === null) {
            return [];
        }

        if ($this->hasFullBranchAccess()) {
            return null;
        }

        return UserBranchAccess::query()
            ->where('tenant_membership_id', $this->membership->id)
            ->pluck('branch_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function canAccessBranch(int $branchId, bool $requireManage = false): bool
    {
        if ($this->membership === null) {
            return false;
        }

        if ($this->hasFullBranchAccess()) {
            return true;
        }

        $query = UserBranchAccess::query()
            ->where('tenant_membership_id', $this->membership->id)
            ->where('branch_id', $branchId);

        if ($requireManage) {
            $query->where('access_level', 'manage');
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, UserBranchAccess>
     */
    public function branchAccessEntries(): Collection
    {
        if ($this->membership === null) {
            return collect();
        }

        return UserBranchAccess::query()
            ->where('tenant_membership_id', $this->membership->id)
            ->get();
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->tenant = null;
        $this->membership = null;
        $this->activeBranchId = null;
        $this->cachedPermissionKeys = null;
        $this->cachedRoleSlugs = null;
    }
}
