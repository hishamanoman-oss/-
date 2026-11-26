<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FieldMapping extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'canonical_entity',
        'source_table',
        'source_column',
        'canonical_field',
        'transform_rule',
        'default_value',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
