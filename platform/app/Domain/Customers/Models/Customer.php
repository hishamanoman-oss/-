<?php

declare(strict_types=1);

namespace App\Domain\Customers\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'name',
        'phone',
        'email',
        'status',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
