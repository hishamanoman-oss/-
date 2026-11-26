<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Presentation\Livewire\SystemStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SystemStatusTest extends TestCase
{
    public function test_api_v1_health_returns_live_database_and_mock_pos_counts(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('service', 'remote-business-platform')
            ->assertJsonPath('checks.rbd_platform.status', 'ok')
            ->assertJsonPath('checks.mock_pos_db.status', 'ok')
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_branches', 3)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_warehouses', 3)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_clients', 16)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_items', 22)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_stock_qty', 44)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_invoices', 28)
            ->assertJsonPath('checks.mock_pos_db.counts.tbl_invoice_lines', 41);
    }

    public function test_mock_pos_connection_enforces_read_only_privileges(): void
    {
        $this->expectException(QueryException::class);

        DB::connection('mock_pos')->table('tbl_branches')->insert([
            'br_name' => 'Unauthorized Write',
            'br_city' => 'None',
        ]);
    }

    public function test_system_status_page_renders_arabic_rtl_and_toggles_to_english_ltr(): void
    {
        $arResponse = $this->get('/system-status');
        $arResponse->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false)
            ->assertSee('tbl_invoices');

        $enResponse = $this->get('/system-status?lang=en');
        $enResponse->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="en"', false)
            ->assertSee('System Health &amp; Environment Status', false);
    }

    public function test_system_status_livewire_component_refreshes_live_report(): void
    {
        Livewire::test(SystemStatus::class)
            ->assertSet('report.checks.mock_pos_db.counts.tbl_items', 22)
            ->call('refreshStatus')
            ->assertSet('report.checks.mock_pos_db.counts.tbl_invoices', 28);
    }
}
