<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Tenants\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_platform_admin',
        'is_active',
        'preferred_locale',
        'preferred_theme',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id')
            ->where('status', 'active')
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'));
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->withPivot(['id', 'status', 'is_default', 'joined_at'])
            ->withTimestamps();
    }
}
