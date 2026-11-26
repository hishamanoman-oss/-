<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConnectorInstance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'data_source_id',
        'device_id',
        'device_name',
        'agent_version',
        'enrollment_code_hash',
        'enrollment_expires_at',
        'token_hash',
        'public_key_ed25519',
        'status',
        'last_seen_at',
        'last_ip',
    ];

    protected $hidden = [
        'enrollment_code_hash',
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
