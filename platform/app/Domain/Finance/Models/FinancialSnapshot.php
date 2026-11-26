<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialSnapshot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'snapshot_date',
        'currency_code',
        'gross_sales',
        'discounts',
        'refunds',
        'net_sales',
        'tax_total',
        'paid_total',
        'outstanding_total',
        'cogs_total',
        'gross_profit',
        'operating_expenses',
        'net_profit',
        'profit_completeness_status',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'gross_sales' => 'decimal:4',
            'discounts' => 'decimal:4',
            'refunds' => 'decimal:4',
            'net_sales' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'paid_total' => 'decimal:4',
            'outstanding_total' => 'decimal:4',
            'cogs_total' => 'decimal:4',
            'gross_profit' => 'decimal:4',
            'operating_expenses' => 'decimal:4',
            'net_profit' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
