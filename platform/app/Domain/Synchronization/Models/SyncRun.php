<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Models;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SyncRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'connector_instance_id',
        'run_type',
        'status',
        'records_received',
        'records_upserted',
        'records_skipped_duplicate',
        'records_failed',
        'started_at',
        'finished_at',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'records_received' => 'integer',
            'records_upserted' => 'integer',
            'records_skipped_duplicate' => 'integer',
            'records_failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public function connectorInstance(): BelongsTo
    {
        return $this->belongsTo(ConnectorInstance::class, 'connector_instance_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(SyncError::class, 'sync_run_id');
    }
}
