<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Throwable;

final class MockPosReadOnlyProbe
{
    /** @var list<string> */
    private const TABLES = [
        'tbl_branches',
        'tbl_warehouses',
        'tbl_clients',
        'tbl_items',
        'tbl_stock_qty',
        'tbl_invoices',
        'tbl_invoice_lines',
    ];

    /**
     * Query live row counts from mock_pos_db using the read-only connection.
     *
     * @return array{status: string, database: string, counts: array<string, int>, error: string|null}
     */
    public function inspect(): array
    {
        $databaseName = (string) config('database.connections.mock_pos.database', 'mock_pos_db');
        $counts = [];

        try {
            $connection = DB::connection('mock_pos');
            foreach (self::TABLES as $table) {
                $counts[$table] = (int) $connection->table($table)->count();
            }

            return [
                'status' => 'ok',
                'database' => $databaseName,
                'counts' => $counts,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'database' => $databaseName,
                'counts' => $counts,
                'error' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Connection failed',
            ];
        }
    }
}
