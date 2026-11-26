<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Services;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Synchronization\Models\SyncRun;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;

final class ReconciliationService
{
    /** @var array<string, string> */
    private const DEFAULT_MOCK_POS_TABLE_MAP = [
        'branch' => 'tbl_branches',
        'warehouse' => 'tbl_warehouses',
        'customer' => 'tbl_clients',
        'product' => 'tbl_items',
        'inventory_balance' => 'tbl_stock_qty',
        'order' => 'tbl_invoices',
        'order_item' => 'tbl_invoice_lines',
    ];

    /**
     * Compare source entity counts with canonical external_mappings counts.
     *
     * @param  array<string, int>|null  $sourceCountsOverride
     * @return array{
     *     sync_run_id: int,
     *     in_sync: bool,
     *     entities: array<string, array{source_table: string, source_count: int, canonical_mapped_count: int, drift: int}>
     * }
     */
    public function reconcile(
        DataSource $source,
        ?LocalMysqlReadOnlyConnector $connector = null,
        ?array $sourceCountsOverride = null,
    ): array {
        $run = SyncRun::query()->create([
            'data_source_id' => $source->id,
            'run_type' => 'reconciliation',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $sourceCounts = $sourceCountsOverride;
        if ($sourceCounts === null) {
            $conn = $connector ?? new LocalMysqlReadOnlyConnector($source);
            $sourceCounts = $conn->tableCounts(array_values(self::DEFAULT_MOCK_POS_TABLE_MAP));
        }

        $mappedCounts = ExternalMapping::query()
            ->where('data_source_id', $source->id)
            ->selectRaw('canonical_entity, COUNT(*) as cnt')
            ->groupBy('canonical_entity')
            ->pluck('cnt', 'canonical_entity')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $entities = [];
        $allZeroDrift = true;

        foreach (self::DEFAULT_MOCK_POS_TABLE_MAP as $entity => $table) {
            $srcCount = (int) ($sourceCounts[$table] ?? $sourceCounts[$entity] ?? 0);
            $canonCount = (int) ($mappedCounts[$entity] ?? 0);
            $drift = $srcCount - $canonCount;

            if ($drift !== 0) {
                $allZeroDrift = false;
            }

            $entities[$entity] = [
                'source_table' => $table,
                'source_count' => $srcCount,
                'canonical_mapped_count' => $canonCount,
                'drift' => $drift,
            ];
        }

        $run->forceFill([
            'status' => $allZeroDrift ? 'succeeded' : 'partial_failed',
            'records_received' => array_sum(array_column($entities, 'source_count')),
            'records_upserted' => array_sum(array_column($entities, 'canonical_mapped_count')),
            'finished_at' => now(),
            'error_summary' => $allZeroDrift ? null : 'Source vs canonical mapping count drift detected.',
        ])->save();

        return [
            'sync_run_id' => $run->id,
            'in_sync' => $allZeroDrift,
            'entities' => $entities,
        ];
    }
}
