using System.Globalization;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using MySqlConnector;
using RemoteSyncAgent.Storage;

namespace RemoteSyncAgent.Capture;

/// <summary>
/// Default capture strategy: strictly read-only incremental polling over allow-listed tables/columns.
/// Zero dynamic SQL strings from remote config; all table specs and column sets are strictly allow-listed.
/// </summary>
public sealed class IncrementalPollingCaptureProvider : IChangeCaptureProvider
{
    public string StrategyName => "incremental_polling";

    public static readonly IReadOnlySet<string> DefaultAllowedTables = new HashSet<string>(StringComparer.OrdinalIgnoreCase)
    {
        "tbl_branches",
        "tbl_warehouses",
        "tbl_clients",
        "tbl_items",
        "tbl_stock_qty",
        "tbl_invoices",
        "tbl_invoice_lines",
    };

    public static bool IsAllowedTable(string tableName)
        => DefaultAllowedTables.Contains(tableName);

    public async Task<CaptureResult> CaptureIncrementalAsync(
        string mysqlConnectionString,
        IReadOnlySet<string> allowedTables,
        SqliteOutboxStore store,
        CancellationToken cancellationToken)
    {
        var events = new List<CapturedEvent>();
        var checkpoints = new List<SyncCheckpoint>();

        await using var conn = new MySqlConnection(mysqlConnectionString);
        await conn.OpenAsync(cancellationToken);

        if (allowedTables.Contains("tbl_branches"))
        {
            await CaptureBranchesAsync(conn, store, events, checkpoints, cancellationToken);
        }

        if (allowedTables.Contains("tbl_warehouses"))
        {
            await CaptureWarehousesAsync(conn, store, events, checkpoints, cancellationToken);
        }

        if (allowedTables.Contains("tbl_clients"))
        {
            await CaptureClientsAsync(conn, store, events, checkpoints, cancellationToken);
        }

        if (allowedTables.Contains("tbl_items"))
        {
            await CaptureItemsAsync(conn, store, events, checkpoints, cancellationToken);
        }

        if (allowedTables.Contains("tbl_stock_qty"))
        {
            await CaptureStockAsync(conn, store, events, checkpoints, cancellationToken);
        }

        if (allowedTables.Contains("tbl_invoices"))
        {
            await CaptureInvoicesAsync(conn, store, events, checkpoints, cancellationToken);
        }

        return new CaptureResult(events, checkpoints);
    }

    private static async Task CaptureBranchesAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_branches");
        var lastId = cp?.LastPk ?? 0;

        await using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT br_id, br_name, br_city
            FROM tbl_branches
            WHERE br_id > @lastId
            ORDER BY br_id ASC
            LIMIT 250;
            """;
        cmd.Parameters.AddWithValue("@lastId", lastId);

        long maxId = lastId;
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            var id = reader.GetInt64("br_id");
            maxId = Math.Max(maxId, id);
            var payload = new Dictionary<string, object?>
            {
                ["br_id"] = id,
                ["br_name"] = reader.GetString("br_name"),
                ["br_city"] = reader.IsDBNull(reader.GetOrdinal("br_city")) ? null : reader.GetString("br_city"),
            };

            events.Add(BuildEvent("branch", id.ToString(CultureInfo.InvariantCulture), payload, "tbl_branches", "br_id", id.ToString(CultureInfo.InvariantCulture)));
        }

        if (maxId > lastId)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_branches", "br_id", maxId.ToString(CultureInfo.InvariantCulture), maxId));
        }
    }

    private static async Task CaptureWarehousesAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_warehouses");
        var lastId = cp?.LastPk ?? 0;

        await using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT wh_id, br_id, wh_name
            FROM tbl_warehouses
            WHERE wh_id > @lastId
            ORDER BY wh_id ASC
            LIMIT 250;
            """;
        cmd.Parameters.AddWithValue("@lastId", lastId);

        long maxId = lastId;
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            var id = reader.GetInt64("wh_id");
            maxId = Math.Max(maxId, id);
            var payload = new Dictionary<string, object?>
            {
                ["wh_id"] = id,
                ["br_id"] = reader.GetInt64("br_id"),
                ["wh_name"] = reader.GetString("wh_name"),
            };

            events.Add(BuildEvent("warehouse", id.ToString(CultureInfo.InvariantCulture), payload, "tbl_warehouses", "wh_id", id.ToString(CultureInfo.InvariantCulture)));
        }

        if (maxId > lastId)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_warehouses", "wh_id", maxId.ToString(CultureInfo.InvariantCulture), maxId));
        }
    }

    private static async Task CaptureClientsAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_clients");
        var lastTs = cp?.LastCursorValue ?? "1970-01-01 00:00:00";
        var lastPk = cp?.LastPk ?? 0;

        await using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT client_no, full_name, mobile_no, email_addr, DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') AS updated_at_str
            FROM tbl_clients
            WHERE updated_at > @lastTs OR (updated_at = @lastTs AND client_no > @lastPk)
            ORDER BY updated_at ASC, client_no ASC
            LIMIT 250;
            """;
        cmd.Parameters.AddWithValue("@lastTs", lastTs);
        cmd.Parameters.AddWithValue("@lastPk", lastPk);

        var maxTs = lastTs;
        var maxPk = lastPk;
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            var id = reader.GetInt64("client_no");
            var ts = reader.GetString("updated_at_str");
            if (string.CompareOrdinal(ts, maxTs) > 0 || (ts == maxTs && id > maxPk))
            {
                maxTs = ts;
                maxPk = id;
            }

            var payload = new Dictionary<string, object?>
            {
                ["client_no"] = id,
                ["full_name"] = reader.GetString("full_name"),
                ["mobile_no"] = reader.IsDBNull(reader.GetOrdinal("mobile_no")) ? null : reader.GetString("mobile_no"),
                ["email_addr"] = reader.IsDBNull(reader.GetOrdinal("email_addr")) ? null : reader.GetString("email_addr"),
                ["updated_at"] = ts,
            };

            events.Add(BuildEvent("customer", id.ToString(CultureInfo.InvariantCulture), payload, "tbl_clients", "updated_at", ts));
        }

        if (maxTs != lastTs || maxPk != lastPk)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_clients", "updated_at", maxTs, maxPk));
        }
    }

    private static async Task CaptureItemsAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_items");
        var lastTs = cp?.LastCursorValue ?? "1970-01-01 00:00:00";
        var lastPk = cp?.LastPk ?? 0;

        await using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT item_code, item_sku, item_title, cat_name, unit_price, cost_price, is_active,
                   DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') AS updated_at_str
            FROM tbl_items
            WHERE updated_at > @lastTs OR (updated_at = @lastTs AND item_code > @lastPk)
            ORDER BY updated_at ASC, item_code ASC
            LIMIT 250;
            """;
        cmd.Parameters.AddWithValue("@lastTs", lastTs);
        cmd.Parameters.AddWithValue("@lastPk", lastPk);

        var maxTs = lastTs;
        var maxPk = lastPk;
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            var id = reader.GetInt64("item_code");
            var ts = reader.GetString("updated_at_str");
            if (string.CompareOrdinal(ts, maxTs) > 0 || (ts == maxTs && id > maxPk))
            {
                maxTs = ts;
                maxPk = id;
            }

            var payload = new Dictionary<string, object?>
            {
                ["item_code"] = id,
                ["item_sku"] = reader.GetString("item_sku"),
                ["item_title"] = reader.GetString("item_title"),
                ["cat_name"] = reader.IsDBNull(reader.GetOrdinal("cat_name")) ? null : reader.GetString("cat_name"),
                ["unit_price"] = reader.GetDecimal("unit_price").ToString("0.0000", CultureInfo.InvariantCulture),
                ["cost_price"] = reader.IsDBNull(reader.GetOrdinal("cost_price"))
                    ? null
                    : reader.GetDecimal("cost_price").ToString("0.0000", CultureInfo.InvariantCulture),
                ["is_active"] = reader.GetBoolean("is_active") ? 1 : 0,
                ["updated_at"] = ts,
            };

            events.Add(BuildEvent("product", id.ToString(CultureInfo.InvariantCulture), payload, "tbl_items", "updated_at", ts));
        }

        if (maxTs != lastTs || maxPk != lastPk)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_items", "updated_at", maxTs, maxPk));
        }
    }

    private static async Task CaptureStockAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_stock_qty");
        var lastTs = cp?.LastCursorValue ?? "1970-01-01 00:00:00";
        var lastComposite = cp?.LastPk ?? 0;

        await using var cmd = conn.CreateCommand();
        cmd.CommandText = """
            SELECT wh_id, item_code, qty_on_hand, qty_reserved,
                   DATE_FORMAT(last_changed_at, '%Y-%m-%d %H:%i:%s') AS changed_at_str,
                   ((wh_id * 1000000) + item_code) AS composite_pk
            FROM tbl_stock_qty
            WHERE last_changed_at > @lastTs OR (last_changed_at = @lastTs AND ((wh_id * 1000000) + item_code) > @lastPk)
            ORDER BY last_changed_at ASC, wh_id ASC, item_code ASC
            LIMIT 250;
            """;
        cmd.Parameters.AddWithValue("@lastTs", lastTs);
        cmd.Parameters.AddWithValue("@lastPk", lastComposite);

        var maxTs = lastTs;
        var maxComposite = lastComposite;
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        while (await reader.ReadAsync(ct))
        {
            var whId = reader.GetInt64("wh_id");
            var itemCode = reader.GetInt64("item_code");
            var compPk = reader.GetInt64("composite_pk");
            var ts = reader.GetString("changed_at_str");
            if (string.CompareOrdinal(ts, maxTs) > 0 || (ts == maxTs && compPk > maxComposite))
            {
                maxTs = ts;
                maxComposite = compPk;
            }

            var extId = $"{whId}-{itemCode}";
            var payload = new Dictionary<string, object?>
            {
                ["wh_id"] = whId,
                ["item_code"] = itemCode,
                ["qty_on_hand"] = reader.GetDecimal("qty_on_hand").ToString("0.0000", CultureInfo.InvariantCulture),
                ["qty_reserved"] = reader.GetDecimal("qty_reserved").ToString("0.0000", CultureInfo.InvariantCulture),
                ["last_changed_at"] = ts,
            };

            events.Add(BuildEvent("inventory_balance", extId, payload, "tbl_stock_qty", "last_changed_at", ts));
        }

        if (maxTs != lastTs || maxComposite != lastComposite)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_stock_qty", "last_changed_at", maxTs, maxComposite));
        }
    }

    private static async Task CaptureInvoicesAsync(
        MySqlConnection conn,
        SqliteOutboxStore store,
        List<CapturedEvent> events,
        List<SyncCheckpoint> checkpoints,
        CancellationToken ct)
    {
        var cp = store.GetCheckpoint("tbl_invoices");
        var lastTs = cp?.LastCursorValue ?? "1970-01-01 00:00:00";
        var lastPk = cp?.LastPk ?? 0;

        var rawInvoices = new List<(long InvId, string UpdatedAtStr, Dictionary<string, object?> Payload)>();

        await using (var cmd = conn.CreateCommand())
        {
            cmd.CommandText = """
                SELECT inv_id, inv_number, br_id, client_no, inv_status,
                       gross_amt, disc_amt, tax_amt, net_amt, paid_amt, currency_code,
                       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at_str,
                       DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') AS updated_at_str
                FROM tbl_invoices
                WHERE updated_at > @lastTs OR (updated_at = @lastTs AND inv_id > @lastPk)
                ORDER BY updated_at ASC, inv_id ASC
                LIMIT 200;
                """;
            cmd.Parameters.AddWithValue("@lastTs", lastTs);
            cmd.Parameters.AddWithValue("@lastPk", lastPk);

            await using var reader = await cmd.ExecuteReaderAsync(ct);
            while (await reader.ReadAsync(ct))
            {
                var invId = reader.GetInt64("inv_id");
                var updatedStr = reader.GetString("updated_at_str");
                var payload = new Dictionary<string, object?>
                {
                    ["inv_id"] = invId,
                    ["inv_number"] = reader.GetString("inv_number"),
                    ["br_id"] = reader.GetInt64("br_id"),
                    ["client_no"] = reader.IsDBNull(reader.GetOrdinal("client_no")) ? null : reader.GetInt64("client_no"),
                    ["inv_status"] = reader.GetString("inv_status"),
                    ["gross_amt"] = reader.GetDecimal("gross_amt").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["disc_amt"] = reader.GetDecimal("disc_amt").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["tax_amt"] = reader.GetDecimal("tax_amt").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["net_amt"] = reader.GetDecimal("net_amt").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["paid_amt"] = reader.GetDecimal("paid_amt").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["currency_code"] = reader.GetString("currency_code"),
                    ["created_at"] = reader.GetString("created_at_str"),
                    ["updated_at"] = updatedStr,
                };
                rawInvoices.Add((invId, updatedStr, payload));
            }
        }

        var maxTs = lastTs;
        var maxPk = lastPk;

        foreach (var (invId, ts, payload) in rawInvoices)
        {
            if (string.CompareOrdinal(ts, maxTs) > 0 || (ts == maxTs && invId > maxPk))
            {
                maxTs = ts;
                maxPk = invId;
            }

            var lines = new List<Dictionary<string, object?>>();
            await using var lineCmd = conn.CreateCommand();
            lineCmd.CommandText = """
                SELECT line_id, inv_id, item_code, qty, unit_price, line_total
                FROM tbl_invoice_lines
                WHERE inv_id = @invId
                ORDER BY line_id ASC;
                """;
            lineCmd.Parameters.AddWithValue("@invId", invId);

            await using var lineReader = await lineCmd.ExecuteReaderAsync(ct);
            while (await lineReader.ReadAsync(ct))
            {
                lines.Add(new Dictionary<string, object?>
                {
                    ["line_id"] = lineReader.GetInt64("line_id"),
                    ["inv_id"] = lineReader.GetInt64("inv_id"),
                    ["item_code"] = lineReader.GetInt64("item_code"),
                    ["qty"] = lineReader.GetDecimal("qty").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["unit_price"] = lineReader.GetDecimal("unit_price").ToString("0.0000", CultureInfo.InvariantCulture),
                    ["line_total"] = lineReader.GetDecimal("line_total").ToString("0.0000", CultureInfo.InvariantCulture),
                });
            }

            payload["items"] = lines;
            events.Add(BuildEvent("order", invId.ToString(CultureInfo.InvariantCulture), payload, "tbl_invoices", "updated_at", ts));
        }

        if (maxTs != lastTs || maxPk != lastPk)
        {
            checkpoints.Add(new SyncCheckpoint("tbl_invoices", "updated_at", maxTs, maxPk));
        }
    }

    public static CapturedEvent BuildEvent(
        string entityType,
        string externalId,
        Dictionary<string, object?> payload,
        string cursorTable,
        string cursorColumn,
        string cursorValue)
    {
        var canonicalJson = JsonSerializer.Serialize(payload);
        var hash = Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(canonicalJson))).ToLowerInvariant();
        var eventId = $"{entityType}:{externalId}:{hash[..16]}";

        return new CapturedEvent(
            EventId: eventId,
            EntityType: entityType,
            ExternalId: externalId,
            Payload: payload,
            CursorTable: cursorTable,
            CursorColumn: cursorColumn,
            CursorValue: cursorValue
        );
    }
}
