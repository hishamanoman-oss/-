<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'requested_by_user_id',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }
}
