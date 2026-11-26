<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Branch extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'city',
        'status',
        'timezone',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'branch_id');
    }

    public function branchAccess(): HasMany
    {
        return $this->hasMany(UserBranchAccess::class, 'branch_id');
    }

    protected static function newFactory(): \Database\Factories\BranchFactory
    {
        return \Database\Factories\BranchFactory::new();
    }
}
