<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class License extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_key_id',
        'edition',
        'payload_json',
        'signature',
        'status',
        'issued_at',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
