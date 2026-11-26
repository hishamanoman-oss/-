<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductVariant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'sku',
        'name',
        'attributes_json',
        'unit_price',
        'cost_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attributes_json' => 'array',
            'unit_price' => 'decimal:4',
            'cost_price' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
