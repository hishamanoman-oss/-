<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Tenants\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TenantMembership extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'is_default',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'tenant_membership_id', 'role_id');
    }

    public function branchAccess(): HasMany
    {
        return $this->hasMany(UserBranchAccess::class, 'tenant_membership_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access', 'tenant_membership_id', 'branch_id')
            ->withPivot('access_level');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
