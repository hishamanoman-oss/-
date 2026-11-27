<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Database\Factories\ScheduledReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScheduledReport extends Model
{
    /** @use HasFactory<ScheduledReportFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'created_by_user_id',
        'name',
        'report_type',
        'frequency',
        'format',
        'recipients_json',
        'filters_json',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients_json' => 'array',
            'filters_json' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ScheduledReportFactory
    {
        return ScheduledReportFactory::new();
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
