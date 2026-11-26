<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Models;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SyncError extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'sync_run_id',
        'entity_type',
        'external_id',
        'error_code',
        'error_message',
        'payload_excerpt_json',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_excerpt_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class, 'sync_run_id');
    }
}
