<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reports\Models\ScheduledReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReport>
 */
final class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'name' => 'Weekly Sales Summary',
            'report_type' => 'sales_summary',
            'frequency' => 'weekly',
            'format' => 'csv',
            'recipients_json' => ['owner@demo.local'],
            'filters_json' => ['period' => '7d'],
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ];
    }
}
