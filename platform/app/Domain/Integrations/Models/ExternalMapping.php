<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExternalMapping extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'canonical_entity',
        'external_id',
        'internal_id',
        'source_hash',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'internal_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
