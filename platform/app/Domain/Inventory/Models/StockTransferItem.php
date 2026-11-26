<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockTransferItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'stock_transfer_id',
        'product_id',
        'variant_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
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
