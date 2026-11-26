<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class Discount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'discount_type',
        'value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
