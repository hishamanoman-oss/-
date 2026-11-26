<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Models;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'provider',
        'delivery_id',
        'signature_valid',
        'payload_json',
        'status',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_json' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
