<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Reports\Mail\ScheduledReportMail;
use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

final class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_csv_xlsx_and_pdf_exports_are_valid_honour_profit_guard_and_write_audit_logs(): void
    {
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();

        // 1. CSV export with UTF-8 BOM
        $csvResponse = $this->actingAs($owner)
            ->get('/reports/export?type=sales_summary&format=csv&period=30d')
            ->assertOk();

        $csvBody = $csvResponse->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvBody);
        $this->assertStringContainsString('Net Sales', $csvBody);

        // 2. Real .xlsx workbook parsed by OpenSpout Reader
        $xlsxResponse = $this->actingAs($owner)
            ->get('/reports/export?type=branch_comparison&format=xlsx&period=30d')
            ->assertOk();

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_xlsx_').'.xlsx';
        file_put_contents($tmpXlsx, $xlsxResponse->getContent());
        try {
            $reader = new Reader;
            $reader->open($tmpXlsx);
            $rowCount = 0;
            $foundHeader = false;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowCount++;
                    $cells = $row->toArray();
                    if (in_array('Branch Code', $cells, true)) {
                        $foundHeader = true;
                    }
                }
            }
            $reader->close();
            $this->assertTrue($foundHeader, 'Exported .xlsx workbook must contain Branch Comparison headers.');
            $this->assertGreaterThanOrEqual(3, $rowCount);
        } finally {
            @unlink($tmpXlsx);
        }

        // 3. Real PDF binary with Financial Statement Profit Completeness Guard honored
        $pdfResponse = $this->actingAs($owner)
            ->get('/reports/export?type=financial_statement&format=pdf&period=30d')
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $pdfResponse->getContent());

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $ctx->bind($tenantA);

        /** @var ReportExportService $exporter */
        $exporter = app(ReportExportService::class);
        $finPayload = $exporter->build('financial_statement', $tenantA->id);
        $netProfitRow = collect($finPayload['rows'])->first(fn (array $r): bool => $r[0] === 'Net Profit');
        $this->assertNotNull($netProfitRow);
        $this->assertStringContainsString('N/A', $netProfitRow[1]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenantA->id,
            'actor_user_id' => $owner->id,
            'action' => 'report.exported',
        ]);

        $ctx->clear();
    }

    public function test_reports_enforce_role_and_branch_authorization_and_run_scheduled_command(): void
    {
        Mail::fake();

        $inventoryUser = User::query()->where('email', 'inventory@alnoor.local')->firstOrFail();
        $branch1User = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        // Inventory manager lacks reports.view -> 403
        $this->actingAs($inventoryUser)->get('/reports')->assertStatus(403);
        $this->actingAs($inventoryUser)->get('/reports/export?type=sales_summary&format=csv')->assertStatus(403);

        // Branch1 user attempting to export Branch 2 report -> 403
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $branchNorth = $ctx->runWithoutTenantScope(fn () => Branch::query()
            ->where('tenant_id', $tenantA->id)
            ->where('code', 'RYD-NORTH')
            ->firstOrFail());

        $this->actingAs($branch1User)
            ->get("/reports/export?type=sales_summary&format=csv&branch_id={$branchNorth->id}")
            ->assertStatus(403);

        // Trigger due scheduled report via Artisan command
        $ctx->bind($tenantA);
        $scheduled = ScheduledReport::query()->firstOrFail();
        $scheduled->forceFill(['next_run_at' => now()->subMinute()])->save();
        $ctx->clear();

        Artisan::call('reports:run-scheduled');

        Mail::assertSent(ScheduledReportMail::class);
        $fresh = $ctx->runWithoutTenantScope(fn () => ScheduledReport::query()->findOrFail($scheduled->id));
        $this->assertNotNull($fresh->last_run_at);
        $this->assertTrue($fresh->next_run_at->isFuture());
    }
}
