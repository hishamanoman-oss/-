<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'payment_reference',
        'method',
        'amount',
        'currency_code',
        'status',
        'idempotency_key',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
