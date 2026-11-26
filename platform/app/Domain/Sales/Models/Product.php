<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'status',
        'base_price',
        'cost_price',
        'currency_code',
        'reorder_point',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'source_updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'product_id');
    }
}
