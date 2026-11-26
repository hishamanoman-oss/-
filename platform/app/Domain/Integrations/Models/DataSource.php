<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Synchronization\Models\SyncCursor;
use App\Domain\Synchronization\Models\SyncError;
use App\Domain\Synchronization\Models\SyncRun;
use App\Domain\Synchronization\Models\WebhookEvent;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DataSource extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'connector_type',
        'status',
        'freshness_status',
        'capture_mode',
        'sync_interval_seconds',
        'capabilities_json',
        'config_encrypted',
        'last_heartbeat_at',
        'last_successful_sync_at',
        'last_error_summary',
    ];

    protected $hidden = [
        'config_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'sync_interval_seconds' => 'integer',
            'capabilities_json' => 'array',
            'config_encrypted' => 'encrypted:array',
            'last_heartbeat_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ConnectorInstance::class, 'data_source_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(FieldMapping::class, 'data_source_id');
    }

    public function externalMappings(): HasMany
    {
        return $this->hasMany(ExternalMapping::class, 'data_source_id');
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class, 'data_source_id');
    }

    public function syncCursors(): HasMany
    {
        return $this->hasMany(SyncCursor::class, 'data_source_id');
    }

    public function syncErrors(): HasMany
    {
        return $this->hasMany(SyncError::class, 'data_source_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'data_source_id');
    }
}
