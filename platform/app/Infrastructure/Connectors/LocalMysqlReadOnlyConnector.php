<?php

declare(strict_types=1);

namespace App\Infrastructure\Connectors;

use App\Domain\Integrations\Contracts\ConnectorCapabilityContract;
use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Synchronization\Models\SyncCursor;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LocalMysqlReadOnlyConnector implements ConnectorInterface
{
    private ?string $dynamicConnectionName = null;

    public function __construct(
        private readonly DataSource $dataSource,
    ) {}

    public function testConnection(): array
    {
        try {
            $conn = $this->connection();
            $row = $conn->selectOne('SELECT VERSION() AS version, DATABASE() AS db');

            return [
                'ok' => true,
                'engine' => (string) ($row->version ?? 'mysql'),
                'database' => (string) ($row->db ?? $this->databaseName()),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function discoverSchema(): array
    {
        $conn = $this->connection();
        $dbName = $this->databaseName();

        $columns = $conn->select(
            'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$dbName]
        );

        $schema = [];
        foreach ($columns as $col) {
            $table = (string) $col->TABLE_NAME;
            $column = (string) $col->COLUMN_NAME;
            $schema[$table][$column] = (string) $col->DATA_TYPE;
        }

        return $schema;
    }

    public function inspectCapabilities(): ConnectorCapabilityContract
    {
        return new ConnectorCapabilityContract(
            supportsRead: true,
            supportsWrite: false,
            supportsCdc: true,
            supportsPolling: true,
            supportsWebhooks: false,
            allowedWriteCommands: [],
        );
    }

    public function fetchChanges(SyncCursor $cursor, int $limit = 500): array
    {
        $schema = $this->discoverSchema();
        $table = $cursor->source_table;
        $cursorCol = $cursor->cursor_column;

        if (! isset($schema[$table]) || ! isset($schema[$table][$cursorCol])) {
            throw new DomainException("Source table [{$table}] or cursor column [{$cursorCol}] not found in discovered schema.");
        }

        $safeLimit = max(1, min(1000, $limit));
        $dataType = strtolower($schema[$table][$cursorCol]);
        $isDateCursor = in_array($dataType, ['datetime', 'timestamp', 'date'], true);

        $query = $this->connection()->table($table);

        if ($cursor->last_cursor_value !== null && $cursor->last_cursor_value !== '') {
            $operator = $isDateCursor ? '>=' : '>';
            $query->where($cursorCol, $operator, $cursor->last_cursor_value);
        }

        $rows = $query->orderBy($cursorCol, 'asc')->limit($safeLimit)->get();

        return array_map(static fn (object $row): array => (array) $row, $rows->all());
    }

    /**
     * Fetch all rows from a whitelisted table safely for full/manual sync.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAllFromTable(string $table, ?string $orderByColumn = null): array
    {
        $schema = $this->discoverSchema();
        if (! isset($schema[$table])) {
            throw new DomainException("Source table [{$table}] not found in discovered schema.");
        }

        $query = $this->connection()->table($table);
        if ($orderByColumn !== null && isset($schema[$table][$orderByColumn])) {
            $query->orderBy($orderByColumn, 'asc');
        }

        return array_map(static fn (object $row): array => (array) $row, $query->get()->all());
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    public function tableCounts(array $tables): array
    {
        $schema = $this->discoverSchema();
        $counts = [];

        foreach ($tables as $table) {
            if (isset($schema[$table])) {
                $counts[$table] = (int) $this->connection()->table($table)->count();
            }
        }

        return $counts;
    }

    public function disconnect(): void
    {
        if ($this->dynamicConnectionName !== null) {
            DB::purge($this->dynamicConnectionName);
            $this->dynamicConnectionName = null;
        }
    }

    private function connection(): ConnectionInterface
    {
        $cfg = is_array($this->dataSource->config_encrypted) ? $this->dataSource->config_encrypted : [];
        $profile = (string) ($cfg['connection_profile'] ?? 'mock_pos');

        if ($profile === 'mock_pos' && empty($cfg['host'])) {
            return DB::connection('mock_pos');
        }

        $this->dynamicConnectionName = 'connector_ds_'.$this->dataSource->id;
        Config::set("database.connections.{$this->dynamicConnectionName}", [
            'driver' => 'mysql',
            'host' => $cfg['host'] ?? '127.0.0.1',
            'port' => $cfg['port'] ?? '3307',
            'database' => $cfg['database'] ?? 'mock_pos_db',
            'username' => $cfg['username'] ?? 'mock_pos_reader',
            'password' => $cfg['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
        ]);

        return DB::connection($this->dynamicConnectionName);
    }

    private function databaseName(): string
    {
        $cfg = is_array($this->dataSource->config_encrypted) ? $this->dataSource->config_encrypted : [];
        if (! empty($cfg['database'])) {
            return (string) $cfg['database'];
        }

        return (string) config('database.connections.mock_pos.database', 'mock_pos_db');
    }
}
