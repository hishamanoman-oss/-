using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Microsoft.Data.Sqlite;
using RemoteSyncAgent.Security;

namespace RemoteSyncAgent.Storage;

public sealed record CapturedEvent(
    string EventId,
    string EntityType,
    string ExternalId,
    Dictionary<string, object?> Payload,
    string CursorTable,
    string CursorColumn,
    string CursorValue
);

public sealed record OutboxEventRow(
    long Id,
    string BatchIdempotencyKey,
    string EventId,
    string EntityType,
    string ExternalId,
    string PayloadJson,
    string? CursorTable,
    string? CursorColumn,
    string? CursorValue,
    int Attempts,
    DateTimeOffset NextAttemptAt
);

public sealed record SyncCheckpoint(
    string SourceTable,
    string CursorColumn,
    string LastCursorValue,
    long LastPk
);

public sealed class SqliteOutboxStore
{
    private readonly string _connectionString;

    public SqliteOutboxStore(string dbPath)
    {
        var dir = Path.GetDirectoryName(dbPath);
        if (!string.IsNullOrWhiteSpace(dir))
        {
            Directory.CreateDirectory(dir);
        }

        _connectionString = new SqliteConnectionStringBuilder
        {
            DataSource = dbPath,
            Mode = SqliteOpenMode.ReadWriteCreate,
            Pooling = false,
        }.ToString();
    }

    public void EnsureInitialized()
    {
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            PRAGMA journal_mode=WAL;

            CREATE TABLE IF NOT EXISTS outbox_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                batch_idempotency_key TEXT NOT NULL,
                event_id TEXT NOT NULL UNIQUE,
                entity_type TEXT NOT NULL,
                external_id TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                cursor_table TEXT NULL,
                cursor_column TEXT NULL,
                cursor_value TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                next_attempt_at TEXT NOT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                delivered_at TEXT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_outbox_status_next
                ON outbox_events(status, next_attempt_at, id);

            CREATE TABLE IF NOT EXISTS sync_checkpoints (
                source_table TEXT PRIMARY KEY,
                cursor_column TEXT NOT NULL,
                last_cursor_value TEXT NOT NULL,
                last_pk INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS agent_state (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            """;
        cmd.ExecuteNonQuery();
    }

    public int EnqueueEvents(IEnumerable<CapturedEvent> events, DateTimeOffset? now = null)
    {
        var clock = (now ?? DateTimeOffset.UtcNow).ToString("O", CultureInfo.InvariantCulture);
        var inserted = 0;

        using var conn = OpenConnection();
        using var tx = conn.BeginTransaction();

        foreach (var ev in events)
        {
            var payloadJson = JsonSerializer.Serialize(ev.Payload);
            var itemKey = ComputeSha256($"item:{ev.EventId}");

            using var cmd = conn.CreateCommand();
            cmd.Transaction = tx;
            cmd.CommandText = """
                INSERT OR IGNORE INTO outbox_events (
                    batch_idempotency_key, event_id, entity_type, external_id, payload_json,
                    cursor_table, cursor_column, cursor_value, status, attempts, next_attempt_at, created_at
                ) VALUES (
                    $bkey, $evId, $entity, $extId, $payload,
                    $cTable, $cCol, $cVal, 'pending', 0, $nextAt, $createdAt
                );
                """;
            cmd.Parameters.AddWithValue("$bkey", itemKey);
            cmd.Parameters.AddWithValue("$evId", ev.EventId);
            cmd.Parameters.AddWithValue("$entity", ev.EntityType);
            cmd.Parameters.AddWithValue("$extId", ev.ExternalId);
            cmd.Parameters.AddWithValue("$payload", payloadJson);
            cmd.Parameters.AddWithValue("$cTable", ev.CursorTable);
            cmd.Parameters.AddWithValue("$cCol", ev.CursorColumn);
            cmd.Parameters.AddWithValue("$cVal", ev.CursorValue);
            cmd.Parameters.AddWithValue("$nextAt", clock);
            cmd.Parameters.AddWithValue("$createdAt", clock);

            inserted += cmd.ExecuteNonQuery();
        }

        tx.Commit();
        return inserted;
    }

    public List<OutboxEventRow> GetDuePendingEvents(int maxBatch, DateTimeOffset? now = null)
    {
        var clock = (now ?? DateTimeOffset.UtcNow).ToString("O", CultureInfo.InvariantCulture);
        var rows = new List<OutboxEventRow>();

        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT id, batch_idempotency_key, event_id, entity_type, external_id, payload_json,
                   cursor_table, cursor_column, cursor_value, attempts, next_attempt_at
            FROM outbox_events
            WHERE status = 'pending' AND next_attempt_at <= $now
            ORDER BY id ASC
            LIMIT $limit;
            """;
        cmd.Parameters.AddWithValue("$now", clock);
        cmd.Parameters.AddWithValue("$limit", Math.Max(1, maxBatch));

        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            rows.Add(new OutboxEventRow(
                Id: reader.GetInt64(0),
                BatchIdempotencyKey: reader.GetString(1),
                EventId: reader.GetString(2),
                EntityType: reader.GetString(3),
                ExternalId: reader.GetString(4),
                PayloadJson: reader.GetString(5),
                CursorTable: reader.IsDBNull(6) ? null : reader.GetString(6),
                CursorColumn: reader.IsDBNull(7) ? null : reader.GetString(7),
                CursorValue: reader.IsDBNull(8) ? null : reader.GetString(8),
                Attempts: reader.GetInt32(9),
                NextAttemptAt: DateTimeOffset.Parse(reader.GetString(10), CultureInfo.InvariantCulture)
            ));
        }

        return rows;
    }

    public void MarkDelivered(IReadOnlyCollection<long> ids, DateTimeOffset? now = null)
    {
        if (ids.Count == 0)
        {
            return;
        }

        var clock = (now ?? DateTimeOffset.UtcNow).ToString("O", CultureInfo.InvariantCulture);
        using var conn = OpenConnection();
        using var tx = conn.BeginTransaction();

        foreach (var id in ids)
        {
            using var cmd = conn.CreateCommand();
            cmd.Transaction = tx;
            cmd.CommandText = """
                UPDATE outbox_events
                SET status = 'delivered',
                    delivered_at = $deliveredAt,
                    last_error = NULL
                WHERE id = $id;
                """;
            cmd.Parameters.AddWithValue("$deliveredAt", clock);
            cmd.Parameters.AddWithValue("$id", id);
            cmd.ExecuteNonQuery();
        }

        tx.Commit();
    }

    public TimeSpan RecordRetryFailure(
        IReadOnlyCollection<long> ids,
        string errorMessage,
        int initialBackoffSeconds,
        int maxBackoffSeconds,
        DateTimeOffset? now = null)
    {
        if (ids.Count == 0)
        {
            return TimeSpan.Zero;
        }

        var utcNow = now ?? DateTimeOffset.UtcNow;
        var sanitizedError = SecretRedactor.Redact(errorMessage);
        if (sanitizedError.Length > 500)
        {
            sanitizedError = sanitizedError[..500];
        }

        var maxDelay = TimeSpan.Zero;
        using var conn = OpenConnection();
        using var tx = conn.BeginTransaction();

        foreach (var id in ids)
        {
            using var selectCmd = conn.CreateCommand();
            selectCmd.Transaction = tx;
            selectCmd.CommandText = "SELECT attempts FROM outbox_events WHERE id = $id;";
            selectCmd.Parameters.AddWithValue("$id", id);
            var currentAttempts = Convert.ToInt32(selectCmd.ExecuteScalar() ?? 0, CultureInfo.InvariantCulture);

            var nextAttempts = currentAttempts + 1;
            var delaySeconds = CalculateExponentialBackoffSeconds(nextAttempts, initialBackoffSeconds, maxBackoffSeconds);
            var nextAttemptAt = utcNow.AddSeconds(delaySeconds);
            if (TimeSpan.FromSeconds(delaySeconds) > maxDelay)
            {
                maxDelay = TimeSpan.FromSeconds(delaySeconds);
            }

            using var updateCmd = conn.CreateCommand();
            updateCmd.Transaction = tx;
            updateCmd.CommandText = """
                UPDATE outbox_events
                SET attempts = $attempts,
                    next_attempt_at = $nextAt,
                    last_error = $err
                WHERE id = $id;
                """;
            updateCmd.Parameters.AddWithValue("$attempts", nextAttempts);
            updateCmd.Parameters.AddWithValue("$nextAt", nextAttemptAt.ToString("O", CultureInfo.InvariantCulture));
            updateCmd.Parameters.AddWithValue("$err", sanitizedError);
            updateCmd.Parameters.AddWithValue("$id", id);
            updateCmd.ExecuteNonQuery();
        }

        tx.Commit();
        return maxDelay;
    }

    public static int CalculateExponentialBackoffSeconds(int attempts, int initialBackoffSeconds, int maxBackoffSeconds)
    {
        var baseSec = Math.Max(1, initialBackoffSeconds);
        var capSec = Math.Max(baseSec, maxBackoffSeconds);
        var shift = Math.Clamp(attempts - 1, 0, 10);
        var computed = baseSec * (1L << shift);
        return (int)Math.Min(capSec, computed);
    }

    public static string ComputeBatchIdempotencyKey(IReadOnlyList<OutboxEventRow> rows)
    {
        var joined = string.Join("|", rows.OrderBy(r => r.Id).Select(r => r.EventId));
        return $"agent-{ComputeSha256(joined)}";
    }

    public int GetPendingCount()
    {
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT COUNT(*) FROM outbox_events WHERE status = 'pending';";
        return Convert.ToInt32(cmd.ExecuteScalar() ?? 0, CultureInfo.InvariantCulture);
    }

    public int GetDeliveredCount()
    {
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT COUNT(*) FROM outbox_events WHERE status = 'delivered';";
        return Convert.ToInt32(cmd.ExecuteScalar() ?? 0, CultureInfo.InvariantCulture);
    }

    public SyncCheckpoint? GetCheckpoint(string sourceTable)
    {
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT source_table, cursor_column, last_cursor_value, last_pk
            FROM sync_checkpoints
            WHERE source_table = $table;
            """;
        cmd.Parameters.AddWithValue("$table", sourceTable);

        using var reader = cmd.ExecuteReader();
        if (!reader.Read())
        {
            return null;
        }

        return new SyncCheckpoint(
            SourceTable: reader.GetString(0),
            CursorColumn: reader.GetString(1),
            LastCursorValue: reader.GetString(2),
            LastPk: reader.GetInt64(3)
        );
    }

    public void UpsertCheckpoint(string sourceTable, string cursorColumn, string lastCursorValue, long lastPk, DateTimeOffset? now = null)
    {
        var clock = (now ?? DateTimeOffset.UtcNow).ToString("O", CultureInfo.InvariantCulture);
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            INSERT INTO sync_checkpoints (source_table, cursor_column, last_cursor_value, last_pk, updated_at)
            VALUES ($table, $col, $val, $pk, $updatedAt)
            ON CONFLICT(source_table) DO UPDATE SET
                cursor_column = excluded.cursor_column,
                last_cursor_value = excluded.last_cursor_value,
                last_pk = excluded.last_pk,
                updated_at = excluded.updated_at;
            """;
        cmd.Parameters.AddWithValue("$table", sourceTable);
        cmd.Parameters.AddWithValue("$col", cursorColumn);
        cmd.Parameters.AddWithValue("$val", lastCursorValue);
        cmd.Parameters.AddWithValue("$pk", lastPk);
        cmd.Parameters.AddWithValue("$updatedAt", clock);
        cmd.ExecuteNonQuery();
    }

    public void SetState(string key, string value, DateTimeOffset? now = null)
    {
        var clock = (now ?? DateTimeOffset.UtcNow).ToString("O", CultureInfo.InvariantCulture);
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            INSERT INTO agent_state (key, value, updated_at)
            VALUES ($k, $v, $t)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at;
            """;
        cmd.Parameters.AddWithValue("$k", key);
        cmd.Parameters.AddWithValue("$v", value);
        cmd.Parameters.AddWithValue("$t", clock);
        cmd.ExecuteNonQuery();
    }

    public string? GetState(string key)
    {
        using var conn = OpenConnection();
        using var cmd = conn.CreateCommand();
        cmd.CommandText = "SELECT value FROM agent_state WHERE key = $k;";
        cmd.Parameters.AddWithValue("$k", key);
        var res = cmd.ExecuteScalar();
        return res is null or DBNull ? null : Convert.ToString(res, CultureInfo.InvariantCulture);
    }

    private SqliteConnection OpenConnection()
    {
        var conn = new SqliteConnection(_connectionString);
        conn.Open();
        return conn;
    }

    private static string ComputeSha256(string raw)
    {
        var hash = SHA256.HashData(Encoding.UTF8.GetBytes(raw));
        return Convert.ToHexString(hash).ToLowerInvariant();
    }
}
