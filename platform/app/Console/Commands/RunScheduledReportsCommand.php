<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Services\AuditLogger;
use App\Domain\Reports\Mail\ScheduledReportMail;
use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Tenants\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class RunScheduledReportsCommand extends Command
{
    protected $signature = 'reports:run-scheduled {--report-id= : Optional specific scheduled report ID}';

    protected $description = 'Execute due scheduled reports, generate CSV/XLSX/PDF artifacts, email recipients, and advance next_run_at.';

    public function handle(
        TenantContext $tenantContext,
        ReportExportService $exportService,
        AuditLogger $audit,
    ): int {
        $specificId = $this->option('report-id');

        $dueReports = $tenantContext->runWithoutTenantScope(function () use ($specificId) {
            return ScheduledReport::query()
                ->with('tenant')
                ->when(
                    $specificId !== null,
                    fn ($q) => $q->where('id', (int) $specificId),
                    fn ($q) => $q->where('is_active', true)
                        ->where(fn ($sq) => $sq->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
                )
                ->orderBy('id')
                ->get();
        });

        $executed = 0;

        foreach ($dueReports as $scheduled) {
            $tenant = $scheduled->tenant;
            if ($tenant === null || $tenant->status !== 'active') {
                continue;
            }

            $tenantContext->bind($tenant);

            try {
                $filters = is_array($scheduled->filters_json) ? $scheduled->filters_json : [];
                $period = (string) ($filters['period'] ?? match ($scheduled->frequency) {
                    'daily' => 'today',
                    'monthly' => 'this_month',
                    default => '7d',
                });

                $payload = $exportService->build(
                    reportType: $scheduled->report_type,
                    tenantId: (int) $tenant->id,
                    allowedBranchIds: null,
                    branchId: $scheduled->branch_id ? (int) $scheduled->branch_id : null,
                    periodKey: $period,
                );

                [$binary, $mime, $ext] = match ($scheduled->format) {
                    'xlsx' => [
                        $exportService->toXlsxBinary($payload),
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'xlsx',
                    ],
                    'pdf' => [
                        $exportService->toPdfBinary($payload),
                        'application/pdf',
                        'pdf',
                    ],
                    default => [
                        $exportService->toCsv($payload),
                        'text/csv; charset=UTF-8',
                        'csv',
                    ],
                };

                $fileName = "{$scheduled->report_type}-".now()->format('Ymd-His').".{$ext}";
                $recipients = is_array($scheduled->recipients_json) ? $scheduled->recipients_json : [];
                foreach ($recipients as $email) {
                    if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Mail::to($email)->send(new ScheduledReportMail(
                            reportName: $scheduled->name,
                            reportTitle: $payload['title'],
                            subtitle: $payload['subtitle'],
                            tenantName: $tenant->name,
                            fileName: $fileName,
                            mimeType: $mime,
                            binaryPayload: $binary,
                        ));
                    }
                }

                $now = CarbonImmutable::now();
                $nextRun = match ($scheduled->frequency) {
                    'daily' => $now->addDay(),
                    'monthly' => $now->addMonth(),
                    default => $now->addWeek(),
                };

                $scheduled->forceFill([
                    'last_run_at' => $now,
                    'next_run_at' => $nextRun,
                ])->save();

                $audit->log(
                    action: 'report.scheduled.executed',
                    entityType: 'scheduled_report',
                    entityId: $scheduled->id,
                    metadata: [
                        'report_type' => $scheduled->report_type,
                        'format' => $scheduled->format,
                        'recipients_count' => count($recipients),
                    ],
                    tenantId: (int) $tenant->id,
                    actorUserId: $scheduled->created_by_user_id ? (int) $scheduled->created_by_user_id : null,
                );

                $executed++;
            } finally {
                $tenantContext->clear();
            }
        }

        $this->info("Executed {$executed} scheduled report(s).");

        return self::SUCCESS;
    }
}
