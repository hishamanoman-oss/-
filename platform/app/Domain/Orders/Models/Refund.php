<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Refund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'payment_id',
        'amount',
        'reason',
        'restock_inventory',
        'idempotency_key',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'restock_inventory' => 'boolean',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
