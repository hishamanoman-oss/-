<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\PerformanceTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceTarget>
 */
final class PerformanceTargetFactory extends Factory
{
    protected $model = PerformanceTarget::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'metric_type' => 'net_sales',
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_value' => '50000.0000',
            'currency_code' => 'SAR',
        ];
    }
}
