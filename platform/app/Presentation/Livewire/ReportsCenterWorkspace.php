<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Analytics\Services\InventoryIntelligenceService;
use App\Domain\Analytics\Services\PerformanceTargetService;
use App\Domain\Analytics\Services\SalesAnalyticsService;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class ReportsCenterWorkspace extends Component
{
    public string $activeTab = 'export';

    // Report Builder filters
    public string $reportType = 'sales_summary';

    public string $period = '30d';

    public string $customFrom = '';

    public string $customTo = '';

    public ?int $selectedBranchId = null;

    // Performance Target form
    public string $targetMetric = 'net_sales';

    public string $targetPeriod = 'monthly';

    public ?int $targetBranchId = null;

    public string $targetValue = '50000.00';

    public string $targetStart = '';

    public string $targetEnd = '';

    // Scheduled Report form
    public string $schedName = '';

    public string $schedType = 'sales_summary';

    public string $schedFrequency = 'weekly';

    public string $schedFormat = 'csv';

    public string $schedRecipients = '';

    public ?int $schedBranchId = null;

    public function mount(TenantContext $tenantContext): void
    {
        Gate::authorize('viewAny', ScheduledReport::class);

        $this->customFrom = now()->subDays(29)->toDateString();
        $this->customTo = now()->toDateString();
        $this->targetStart = now()->startOfMonth()->toDateString();
        $this->targetEnd = now()->endOfMonth()->toDateString();
        $this->selectedBranchId = $tenantContext->activeBranchId();
        $this->schedRecipients = (string) (Auth::user()?->email ?? '');
    }

    public function createTarget(TenantContext $tenantContext): void
    {
        Gate::authorize('manage', PerformanceTarget::class);

        $validated = $this->validate([
            'targetMetric' => ['required', 'in:net_sales,orders_count,paid_revenue'],
            'targetPeriod' => ['required', 'in:daily,weekly,monthly'],
            'targetBranchId' => ['nullable', 'integer'],
            'targetValue' => ['required', 'numeric', 'gt:0'],
            'targetStart' => ['required', 'date'],
            'targetEnd' => ['required', 'date', 'after_or_equal:targetStart'],
        ]);

        if ($validated['targetBranchId'] !== null && ! $tenantContext->canAccessBranch((int) $validated['targetBranchId'])) {
            $this->addError('targetBranchId', 'Unauthorized branch.');

            return;
        }

        PerformanceTarget::query()->create([
            'branch_id' => $validated['targetBranchId'] ?: null,
            'metric_type' => $validated['targetMetric'],
            'period_type' => $validated['targetPeriod'],
            'period_start' => $validated['targetStart'],
            'period_end' => $validated['targetEnd'],
            'target_value' => number_format((float) $validated['targetValue'], 4, '.', ''),
            'currency_code' => $tenantContext->tenant()?->default_currency ?? 'SAR',
            'created_by_user_id' => Auth::id(),
        ]);

        $this->dispatch('toast', message: __('ui.target_created_toast'), type: 'success');
    }

    public function deleteTarget(int $targetId): void
    {
        $target = PerformanceTarget::query()->findOrFail($targetId);
        Gate::authorize('update', $target);
        $target->delete();
    }

    public function createScheduledReport(TenantContext $tenantContext): void
    {
        Gate::authorize('create', ScheduledReport::class);

        $validated = $this->validate([
            'schedName' => ['required', 'string', 'max:150'],
            'schedType' => ['required', 'in:'.implode(',', ReportExportService::SUPPORTED_TYPES)],
            'schedFrequency' => ['required', 'in:daily,weekly,monthly'],
            'schedFormat' => ['required', 'in:csv,xlsx,pdf'],
            'schedRecipients' => ['required', 'string', 'max:500'],
            'schedBranchId' => ['nullable', 'integer'],
        ]);

        $emails = array_values(array_filter(
            array_map('trim', explode(',', $validated['schedRecipients'])),
            fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL)
        ));

        if ($emails === []) {
            $this->addError('schedRecipients', __('ui.valid_recipient_email_required'));

            return;
        }

        if ($validated['schedBranchId'] !== null && ! $tenantContext->canAccessBranch((int) $validated['schedBranchId'])) {
            $this->addError('schedBranchId', 'Unauthorized branch.');

            return;
        }

        ScheduledReport::query()->create([
            'branch_id' => $validated['schedBranchId'] ?: null,
            'created_by_user_id' => Auth::id(),
            'name' => trim($validated['schedName']),
            'report_type' => $validated['schedType'],
            'frequency' => $validated['schedFrequency'],
            'format' => $validated['schedFormat'],
            'recipients_json' => $emails,
            'filters_json' => ['period' => $validated['schedFrequency'] === 'daily' ? 'today' : ($validated['schedFrequency'] === 'monthly' ? 'this_month' : '7d')],
            'is_active' => true,
            'next_run_at' => now()->addMinutes(15),
        ]);

        $this->schedName = '';
        $this->dispatch('toast', message: __('ui.scheduled_report_created_toast'), type: 'success');
    }

    public function toggleScheduled(int $reportId): void
    {
        $report = ScheduledReport::query()->findOrFail($reportId);
        Gate::authorize('update', $report);

        $report->forceFill(['is_active' => ! $report->is_active])->save();
    }

    public function runScheduledNow(int $reportId): void
    {
        $report = ScheduledReport::query()->findOrFail($reportId);
        Gate::authorize('update', $report);

        Artisan::call('reports:run-scheduled', ['--report-id' => $report->id]);
        $this->dispatch('toast', message: __('ui.scheduled_report_executed_toast'), type: 'success');
    }

    public function render(
        TenantContext $tenantContext,
        ReportExportService $exportService,
        PerformanceTargetService $targetService,
        SalesAnalyticsService $salesAnalytics,
        InventoryIntelligenceService $inventoryIntelligence,
    ): View {
        Gate::authorize('viewAny', ScheduledReport::class);

        $tenantId = $tenantContext->id();
        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $effectiveBranchId = $this->selectedBranchId;

        if ($effectiveBranchId !== null && ! $tenantContext->canAccessBranch($effectiveBranchId)) {
            $effectiveBranchId = $tenantContext->activeBranchId();
        }

        $preview = $exportService->build(
            reportType: $this->reportType,
            tenantId: $tenantId,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $effectiveBranchId,
            periodKey: $this->period,
            customFrom: $this->customFrom !== '' ? $this->customFrom : null,
            customTo: $this->customTo !== '' ? $this->customTo : null,
        );

        $targets = $targetService->evaluateTargets(
            tenantId: $tenantId,
            allowedBranchIds: $accessibleBranchIds,
            branchId: $effectiveBranchId,
        );

        $scheduledReports = ScheduledReport::query()
            ->with('branch:id,name,code')
            ->latest('id')
            ->get();

        $branchesQuery = Branch::query()->orderBy('id');
        if (is_array($accessibleBranchIds)) {
            $branchesQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        $branches = $branchesQuery->get(['id', 'name', 'code']);

        // Build contextual Chart.js dataset for /reports preview
        $salesSummary = $salesAnalytics->analyze($tenantId, $accessibleBranchIds, $effectiveBranchId);
        $trend = $salesAnalytics->dailyTrend($tenantId, $accessibleBranchIds, $effectiveBranchId);
        $invSummary = $inventoryIntelligence->analyze($tenantId, $accessibleBranchIds, $effectiveBranchId);

        $reportChartSpec = match ($this->reportType) {
            'branch_comparison' => [
                'type' => 'bar',
                'labels' => array_map(fn (array $b): string => $b['branch_name'], $salesSummary['top_branches']),
                'datasets' => [
                    ['label' => __('ui.net_sales'), 'data' => array_map(fn (array $b): float => round((float) $b['net_sales'], 2), $salesSummary['top_branches']), 'color' => '#0284c7'],
                    ['label' => __('ui.paid_revenue'), 'data' => array_map(fn (array $b): float => round((float) $b['paid_total'], 2), $salesSummary['top_branches']), 'color' => '#10b981'],
                ],
            ],
            'inventory_health' => [
                'type' => 'doughnut',
                'labels' => [__('ui.chart_healthy_skus'), __('ui.chart_low_stock'), __('ui.chart_out_of_stock'), __('ui.chart_slow_moving')],
                'datasets' => [[
                    'data' => [
                        max(0, $invSummary['total_skus'] - $invSummary['low_stock_count'] - $invSummary['out_of_stock_count']),
                        $invSummary['low_stock_count'],
                        $invSummary['out_of_stock_count'],
                        $invSummary['slow_moving_count'],
                    ],
                    'colors' => ['#10b981', '#f59e0b', '#e11d48', '#6366f1'],
                ]],
            ],
            default => [
                'type' => 'line',
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => __('ui.chart_current_period'), 'data' => $trend['current_net_sales'], 'color' => '#0284c7', 'fill' => true],
                    ['label' => __('ui.chart_previous_period'), 'data' => $trend['previous_net_sales'], 'color' => '#94a3b8', 'dashed' => true],
                ],
            ],
        };

        $exportQueryBase = array_filter([
            'type' => $this->reportType,
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->customFrom : null,
            'to' => $this->period === 'custom' ? $this->customTo : null,
            'branch_id' => $effectiveBranchId,
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        return view('livewire.reports-center-workspace', [
            'preview' => $preview,
            'targets' => $targets,
            'scheduledReports' => $scheduledReports,
            'branches' => $branches,
            'reportChartSpec' => $reportChartSpec,
            'exportQueryBase' => $exportQueryBase,
            'currency' => $tenantContext->tenant()?->default_currency ?? 'SAR',
        ]);
    }
}
