<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'order_number',
        'order_status',
        'payment_status',
        'fulfillment_status',
        'currency_code',
        'gross_sales',
        'discount_total',
        'tax_total',
        'net_sales',
        'paid_total',
        'refunded_total',
        'outstanding_total',
        'cogs_total',
        'has_complete_cost_data',
        'placed_at',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_sales' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'net_sales' => 'decimal:4',
            'paid_total' => 'decimal:4',
            'refunded_total' => 'decimal:4',
            'outstanding_total' => 'decimal:4',
            'cogs_total' => 'decimal:4',
            'has_complete_cost_data' => 'boolean',
            'placed_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'order_id');
    }
}
