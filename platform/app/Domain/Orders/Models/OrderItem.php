<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'variant_id',
        'sku_snapshot',
        'product_name_snapshot',
        'quantity',
        'unit_price_snapshot',
        'unit_cost_snapshot',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price_snapshot' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
