<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockReservation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'order_id',
        'quantity',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'expires_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
