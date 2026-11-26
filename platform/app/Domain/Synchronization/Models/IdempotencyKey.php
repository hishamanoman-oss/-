<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class IdempotencyKey extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'scope',
        'key_hash',
        'response_code',
        'response_body_json',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'response_body_json' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
