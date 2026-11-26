<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class Tax extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'rate_percent',
        'is_inclusive',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
