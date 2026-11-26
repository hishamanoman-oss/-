<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Models;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_currency',
        'default_timezone',
        'default_locale',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class, 'tenant_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'tenant_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'tenant_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'tenant_id');
    }

    protected static function newFactory(): \Database\Factories\TenantFactory
    {
        return \Database\Factories\TenantFactory::new();
    }
}
