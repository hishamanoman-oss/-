<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryBalance extends Model
{
    use BelongsToTenant;

    public const CREATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'on_hand',
        'reserved',
        'available',
        'version',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:4',
            'reserved' => 'decimal:4',
            'available' => 'decimal:4',
            'version' => 'integer',
            'updated_at' => 'datetime',
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
}
