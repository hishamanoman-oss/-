<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Models;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SyncCursor extends Model
{
    use BelongsToTenant;

    public const CREATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'source_table',
        'cursor_column',
        'last_cursor_value',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
