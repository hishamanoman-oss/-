<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Application\Services\AuditLogger;
use App\Domain\Analytics\Services\AnalyticsPeriod;
use App\Domain\Analytics\Services\CustomerIntelligenceService;
use App\Domain\Analytics\Services\InventoryIntelligenceService;
use App\Domain\Analytics\Services\SalesAnalyticsService;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Reports\Support\ArabicPdfTextHelper;
use App\Domain\Tenants\Services\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class ReportExportService
{
    /** @var list<string> */
    public const SUPPORTED_TYPES = [
        'sales_summary',
        'branch_comparison',
        'inventory_health',
        'customer_ltv',
        'financial_statement',
        'audit_activity',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SalesAnalyticsService $salesAnalytics,
        private readonly InventoryIntelligenceService $inventoryIntelligence,
        private readonly CustomerIntelligenceService $customerIntelligence,
        private readonly FinancialMetricsService $financialMetrics,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Build a structured, strictly tenant- and branch-scoped report payload.
     *
     * @param  list<int>|null  $allowedBranchIds
     * @return array{
     *     report_type: string,
     *     title: string,
     *     subtitle: string,
     *     generated_at: string,
     *     headers: list<string>,
     *     rows: list<list<string>>,
     *     notes: list<string>
     * }
     */
    public function build(
        string $reportType,
        ?int $tenantId = null,
        ?array $allowedBranchIds = null,
        ?int $branchId = null,
        string $periodKey = '30d',
        ?string $customFrom = null,
        ?string $customTo = null,
    ): array {
        if (! in_array($reportType, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported report type [{$reportType}].");
        }

        $resolvedTenantId = $tenantId ?? $this->tenantContext->id();
        $window = AnalyticsPeriod::resolve($periodKey, $customFrom, $customTo);

        $periodLabel = $window->start->toDateString().' .. '.$window->end->toDateString();

        return match ($reportType) {
            'sales_summary' => $this->buildSalesSummary($resolvedTenantId, $allowedBranchIds, $branchId, $window, $periodLabel),
            'branch_comparison' => $this->buildBranchComparison($resolvedTenantId, $allowedBranchIds, $branchId, $window, $periodLabel),
            'inventory_health' => $this->buildInventoryHealth($resolvedTenantId, $allowedBranchIds, $branchId, $window, $periodLabel),
            'customer_ltv' => $this->buildCustomerLtv($resolvedTenantId, $allowedBranchIds, $branchId, $periodLabel),
            'financial_statement' => $this->buildFinancialStatement($resolvedTenantId, $allowedBranchIds, $branchId, $window, $periodLabel),
            'audit_activity' => $this->buildAuditActivity($resolvedTenantId, $window, $periodLabel),
        };
    }

    /**
     * @param  array{report_type: string, title: string, subtitle: string, generated_at: string, headers: list<string>, rows: list<list<string>>, notes: list<string>}  $payload
     */
    public function toCsv(array $payload): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open memory stream for CSV export.');
        }

        // UTF-8 BOM so Arabic renders properly in Microsoft Excel
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map($this->sanitizeCsvCell(...), $payload['headers']));

        foreach ($payload['rows'] as $row) {
            fputcsv($handle, array_map($this->sanitizeCsvCell(...), $row));
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  array{report_type: string, title: string, subtitle: string, generated_at: string, headers: list<string>, rows: list<list<string>>, notes: list<string>}  $payload
     */
    public function toXlsxBinary(array $payload): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'rbd_xlsx_');
        if ($tempPath === false) {
            throw new \RuntimeException('Unable to allocate temporary file for XLSX export.');
        }

        $xlsxPath = $tempPath.'.xlsx';
        @unlink($tempPath);

        try {
            $writer = new Writer;
            $writer->openToFile($xlsxPath);
            $writer->addRow(Row::fromValues([$payload['title'], $payload['subtitle']]));
            $writer->addRow(Row::fromValues($payload['headers']));

            foreach ($payload['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            foreach ($payload['notes'] as $note) {
                $writer->addRow(Row::fromValues([$note]));
            }

            $writer->close();

            return (string) file_get_contents($xlsxPath);
        } finally {
            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }
        }
    }

    /**
     * @param  array{report_type: string, title: string, subtitle: string, generated_at: string, headers: list<string>, rows: list<list<string>>, notes: list<string>}  $payload
     */
    public function toPdfBinary(array $payload): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $html = $this->renderPdfHtml($payload);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function recordExportAudit(
        int $tenantId,
        ?int $actorUserId,
        string $reportType,
        string $format,
        ?int $branchId = null,
    ): void {
        $this->audit->log(
            action: 'report.exported',
            entityType: 'report',
            entityId: null,
            metadata: [
                'report_type' => $reportType,
                'format' => $format,
                'branch_id' => $branchId,
            ],
            tenantId: $tenantId,
            actorUserId: $actorUserId,
        );
    }

    private function sanitizeCsvCell(string $value): string
    {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        // Preserve negative decimals cleanly while guarding formula-like "-FUNC" payloads
        if ($trimmed !== '' && $trimmed[0] === '-' && ! preg_match('/^-[0-9]+(\.[0-9]+)?$/', $trimmed)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<int>|null  $allowedBranchIds
     */
    private function buildSalesSummary(?int $tenantId, ?array $allowedBranchIds, ?int $branchId, AnalyticsPeriod $window, string $periodLabel): array
    {
        $sales = $this->salesAnalytics->analyze(
            tenantId: $tenantId,
            allowedBranchIds: $allowedBranchIds,
            branchId: $branchId,
            period: $window,
            topLimit: 20,
        );

        $rows = [
            ['Gross Sales', number_format((float) $sales['gross_sales'], 2, '.', ''), 'Period total before discounts/refunds'],
            ['Discounts', number_format((float) $sales['discounts'], 2, '.', ''), 'Promotional & invoice discounts'],
            ['Refunds', number_format((float) $sales['refunds'], 2, '.', ''), 'Issued refunds in window'],
            ['Net Sales', number_format((float) $sales['net_sales'], 2, '.', ''), 'Gross - Discounts - Refunds'],
            ['Tax Total', number_format((float) $sales['tax_total'], 2, '.', ''), 'VAT collected'],
            ['Paid Revenue', number_format((float) $sales['paid_total'], 2, '.', ''), 'Settled customer payments'],
            ['Outstanding Receivables', number_format((float) $sales['outstanding_total'], 2, '.', ''), 'Unpaid / partial balances'],
            ['Orders Count', (string) $sales['orders_count'], 'Active non-cancelled orders'],
            ['Cancelled Orders', (string) $sales['cancelled_orders_count'], 'Cancelled during window'],
        ];

        return [
            'report_type' => 'sales_summary',
            'title' => 'Sales Summary Report',
            'subtitle' => "Period: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Metric', 'Value', 'Notes'],
            'rows' => $rows,
            'notes' => ['Generated strictly from tenant & branch scoped canonical orders.'],
        ];
    }

    /**
     * @param  list<int>|null  $allowedBranchIds
     */
    private function buildBranchComparison(?int $tenantId, ?array $allowedBranchIds, ?int $branchId, AnalyticsPeriod $window, string $periodLabel): array
    {
        $sales = $this->salesAnalytics->analyze(
            tenantId: $tenantId,
            allowedBranchIds: $allowedBranchIds,
            branchId: $branchId,
            period: $window,
            topLimit: 50,
        );

        $branches = Branch::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when(
                $branchId !== null,
                fn ($q) => $q->where('id', $branchId),
                fn ($q) => is_array($allowedBranchIds)
                    ? $q->whereIn('id', $allowedBranchIds === [] ? [-1] : $allowedBranchIds)
                    : $q
            )
            ->orderBy('id')
            ->get();

        $bucketMap = [];
        foreach ($sales['top_branches'] as $tb) {
            $bucketMap[$tb['branch_id']] = $tb;
        }

        $rows = [];
        foreach ($branches as $branch) {
            $b = $bucketMap[$branch->id] ?? null;
            $net = $b['net_sales'] ?? DecimalMath::zero();
            $paid = $b['paid_total'] ?? DecimalMath::zero();
            $rate = DecimalMath::gt($net, '0')
                ? DecimalMath::mul(DecimalMath::div($paid, $net), '100')
                : DecimalMath::zero();

            $rows[] = [
                $branch->code,
                $branch->name,
                (string) ($b['orders_count'] ?? 0),
                number_format((float) $net, 2, '.', ''),
                number_format((float) $paid, 2, '.', ''),
                number_format((float) $rate, 1, '.', '').'%',
            ];
        }

        return [
            'report_type' => 'branch_comparison',
            'title' => 'Branch Performance Comparison',
            'subtitle' => "Period: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Branch Code', 'Branch Name', 'Orders', 'Net Sales', 'Paid Revenue', 'Collection Rate'],
            'rows' => $rows,
            'notes' => ['Restricted strictly to user permitted branches.'],
        ];
    }

    /**
     * @param  list<int>|null  $allowedBranchIds
     */
    private function buildInventoryHealth(?int $tenantId, ?array $allowedBranchIds, ?int $branchId, AnalyticsPeriod $window, string $periodLabel): array
    {
        $intel = $this->inventoryIntelligence->analyze(
            tenantId: $tenantId,
            allowedBranchIds: $allowedBranchIds,
            branchId: $branchId,
            period: $window,
        );

        $rows = [];
        foreach ($intel['warehouse_summary'] as $wh) {
            $rows[] = [
                $wh['warehouse_code'],
                $wh['warehouse_name'],
                $wh['branch_name'],
                number_format((float) $wh['total_on_hand'], 2, '.', ''),
                number_format((float) $wh['total_available'], 2, '.', ''),
                (string) $wh['low_stock_count'],
            ];
        }

        $val = $intel['valuation'];
        $notes = [
            "Total SKUs: {$intel['total_skus']} | Low Stock: {$intel['low_stock_count']} | Out of Stock: {$intel['out_of_stock_count']} | Slow Moving: {$intel['slow_moving_count']}",
            'Inventory Cost Valuation (Valued SKUs Only): '.number_format((float) $val['total_valuation'], 2, '.', '')
                ." (Valued SKUs: {$val['valued_sku_count']}, Missing Cost SKUs: {$val['missing_cost_sku_count']})",
        ];

        return [
            'report_type' => 'inventory_health',
            'title' => 'Inventory Health & Valuation Report',
            'subtitle' => "Window: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Warehouse Code', 'Warehouse Name', 'Branch', 'On Hand', 'Available', 'Low-Stock Alerts'],
            'rows' => $rows,
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<int>|null  $allowedBranchIds
     */
    private function buildCustomerLtv(?int $tenantId, ?array $allowedBranchIds, ?int $branchId, string $periodLabel): array
    {
        $crm = $this->customerIntelligence->analyze(
            tenantId: $tenantId,
            allowedBranchIds: $allowedBranchIds,
            branchId: $branchId,
            topLimit: 50,
        );

        $rows = [];
        foreach ($crm['customers'] as $c) {
            $rows[] = [
                $c['code'],
                $c['name'],
                (string) $c['orders_count'],
                number_format((float) $c['lifetime_value'], 2, '.', ''),
                number_format((float) $c['paid_total'], 2, '.', ''),
                number_format((float) $c['outstanding_balance'], 2, '.', ''),
                $c['last_order_at'] ?? '—',
            ];
        }

        return [
            'report_type' => 'customer_ltv',
            'title' => 'Customer LTV & Receivables Report',
            'subtitle' => "Snapshot Window: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Code', 'Customer', 'Orders', 'Lifetime Value', 'Paid Total', 'Receivables', 'Last Order'],
            'rows' => $rows,
            'notes' => ["Total Customers: {$crm['total_customers']} | Repeat Customers: {$crm['repeat_customers_count']}"],
        ];
    }

    /**
     * Strictly enforces the Net Profit Completeness Guard: NEVER fabricates Net Profit.
     *
     * @param  list<int>|null  $allowedBranchIds
     */
    private function buildFinancialStatement(?int $tenantId, ?array $allowedBranchIds, ?int $branchId, AnalyticsPeriod $window, string $periodLabel): array
    {
        $fin = $this->financialMetrics->summarize(
            branchId: $branchId,
            allowedBranchIds: $allowedBranchIds,
            from: $window->start,
            to: $window->end,
        );

        $status = $fin['profit_completeness_status'];
        $cogsLabel = $fin['cogs_total'] !== null ? number_format((float) $fin['cogs_total'], 2, '.', '') : 'N/A — Incomplete Cost Data';
        $grossProfitLabel = $fin['gross_profit'] !== null ? number_format((float) $fin['gross_profit'], 2, '.', '') : 'N/A — Incomplete Cost Data';
        $expensesLabel = $fin['operating_expenses'] !== null ? number_format((float) $fin['operating_expenses'], 2, '.', '') : 'N/A — Operating Expenses Not Entered';
        $netProfitLabel = ($status === 'complete' && $fin['net_profit'] !== null)
            ? number_format((float) $fin['net_profit'], 2, '.', '')
            : 'N/A — Net Profit Guard Active (Incomplete Cost/Expenses Data)';

        $rows = [
            ['Gross Sales', number_format((float) $fin['gross_sales'], 2, '.', '')],
            ['Discounts', number_format((float) $fin['discounts'], 2, '.', '')],
            ['Refunds', number_format((float) $fin['refunds'], 2, '.', '')],
            ['Net Sales', number_format((float) $fin['net_sales'], 2, '.', '')],
            ['Tax Total', number_format((float) $fin['tax_total'], 2, '.', '')],
            ['Paid Revenue', number_format((float) $fin['paid_total'], 2, '.', '')],
            ['Outstanding Receivables', number_format((float) $fin['outstanding_total'], 2, '.', '')],
            ['COGS Total', $cogsLabel],
            ['Gross Profit', $grossProfitLabel],
            ['Operating Expenses', $expensesLabel],
            ['Net Profit', $netProfitLabel],
            ['Profit Completeness Status', $status],
        ];

        $notes = [];
        if ($status !== 'complete') {
            $notes[] = "NET PROFIT GUARD ACTIVE ({$status}): Incomplete cost items orders = {$fin['incomplete_cost_orders_count']}. Net Profit withheld to preserve accounting accuracy.";
        }

        return [
            'report_type' => 'financial_statement',
            'title' => 'Financial Statement Report',
            'subtitle' => "Period: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['Financial Line Item', 'Amount / Status'],
            'rows' => $rows,
            'notes' => $notes,
        ];
    }

    private function buildAuditActivity(?int $tenantId, AnalyticsPeriod $window, string $periodLabel): array
    {
        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$window->start, $window->end])
            ->latest('id')
            ->limit(200)
            ->get();

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = [
                (string) $log->id,
                $log->created_at?->format('Y-m-d H:i:s') ?? '—',
                $log->actor?->name ?? 'System / Agent',
                (string) $log->action,
                (string) ($log->entity_type ?? '—'),
                (string) ($log->entity_id ?? '—'),
            ];
        }

        return [
            'report_type' => 'audit_activity',
            'title' => 'Security & Operational Audit Log',
            'subtitle' => "Period: {$periodLabel}",
            'generated_at' => now()->toDateTimeString(),
            'headers' => ['ID', 'Timestamp', 'Actor', 'Action', 'Entity Type', 'Entity ID'],
            'rows' => $rows,
            'notes' => ['Immutable tenant audit trail (max 200 latest entries per export).'],
        ];
    }

    /**
     * @param  array{report_type: string, title: string, subtitle: string, generated_at: string, headers: list<string>, rows: list<list<string>>, notes: list<string>}  $payload
     */
    private function renderPdfHtml(array $payload): string
    {
        $title = htmlspecialchars(ArabicPdfTextHelper::format($payload['title']), ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars(ArabicPdfTextHelper::format($payload['subtitle']), ENT_QUOTES, 'UTF-8');
        $generated = htmlspecialchars($payload['generated_at'], ENT_QUOTES, 'UTF-8');

        $headerCells = '';
        foreach ($payload['headers'] as $h) {
            $headerCells .= '<th>'.htmlspecialchars(ArabicPdfTextHelper::format($h), ENT_QUOTES, 'UTF-8').'</th>';
        }

        $bodyRows = '';
        foreach ($payload['rows'] as $row) {
            $bodyRows .= '<tr>';
            foreach ($row as $cell) {
                $bodyRows .= '<td>'.htmlspecialchars(ArabicPdfTextHelper::format((string) $cell), ENT_QUOTES, 'UTF-8').'</td>';
            }
            $bodyRows .= '</tr>';
        }

        $notesHtml = '';
        foreach ($payload['notes'] as $note) {
            $notesHtml .= '<p class="note">'.htmlspecialchars(ArabicPdfTextHelper::format($note), ENT_QUOTES, 'UTF-8').'</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #0f172a; }
    .header { border-bottom: 2px solid #0f4c81; padding-bottom: 8px; margin-bottom: 14px; }
    .header h1 { margin: 0; font-size: 16px; color: #0f4c81; }
    .header p { margin: 4px 0 0; font-size: 10px; color: #475569; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #0f172a; color: #ffffff; text-align: left; padding: 7px 8px; font-size: 10px; }
    td { border-bottom: 1px solid #cbd5e1; padding: 6px 8px; font-size: 10px; }
    tr:nth-child(even) td { background: #f8fafc; }
    .note { margin-top: 10px; padding: 8px; background: #fef3c7; border: 1px solid #f59e0b; font-size: 10px; color: #78350f; }
</style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <p>{$subtitle} &bull; Generated: {$generated}</p>
    </div>
    <table>
        <thead><tr>{$headerCells}</tr></thead>
        <tbody>{$bodyRows}</tbody>
    </table>
    {$notesHtml}
</body>
</html>
HTML;
    }
}
