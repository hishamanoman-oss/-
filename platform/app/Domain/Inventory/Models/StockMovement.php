<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'variant_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'actor_user_id',
        'notes',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Stock movements are strictly immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Stock movements are strictly immutable and cannot be deleted.');
        });
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
