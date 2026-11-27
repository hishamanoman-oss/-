<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Database\Factories\PerformanceTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PerformanceTarget extends Model
{
    /** @use HasFactory<PerformanceTargetFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'metric_type',
        'period_type',
        'period_start',
        'period_end',
        'target_value',
        'currency_code',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'target_value' => 'decimal:4',
        ];
    }

    protected static function newFactory(): PerformanceTargetFactory
    {
        return PerformanceTargetFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
