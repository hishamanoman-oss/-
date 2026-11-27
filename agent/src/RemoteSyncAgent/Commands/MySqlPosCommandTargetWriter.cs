using System.Globalization;
using System.Text.Json;
using MySqlConnector;

namespace RemoteSyncAgent.Commands;

public sealed record CommandWriteOutcome(
    bool Success,
    string? ErrorCode,
    string? ErrorMessage,
    Dictionary<string, object?>? Result
);

public interface ICommandTargetWriter
{
    Task<CommandWriteOutcome> ExecuteWriteAsync(
        string writeConnectionString,
        PendingCommandEnvelope command,
        CancellationToken ct);
}

public sealed class MySqlPosCommandTargetWriter : ICommandTargetWriter
{
    public async Task<CommandWriteOutcome> ExecuteWriteAsync(
        string writeConnectionString,
        PendingCommandEnvelope command,
        CancellationToken ct)
    {
        await using var conn = new MySqlConnection(writeConnectionString);
        await conn.OpenAsync(ct);
        await using var tx = await conn.BeginTransactionAsync(ct);

        try
        {
            CommandWriteOutcome outcome = command.CommandType switch
            {
                "product.price.update" => await UpdateProductPriceAsync(conn, tx, command, ct),
                "order.status.update" => await UpdateOrderStatusAsync(conn, tx, command, ct),
                "inventory.stock.adjust" => await AdjustInventoryStockAsync(conn, tx, command, ct),
                _ => new CommandWriteOutcome(false, "UNSUPPORTED_COMMAND", $"Unsupported command type [{command.CommandType}].", null),
            };

            if (outcome.Success)
            {
                await tx.CommitAsync(ct);
            }
            else
            {
                await tx.RollbackAsync(ct);
            }

            return outcome;
        }
        catch (Exception ex)
        {
            await tx.RollbackAsync(ct);
            return new CommandWriteOutcome(false, "MYSQL_WRITE_ERROR", ex.Message, null);
        }
    }

    private static async Task<CommandWriteOutcome> UpdateProductPriceAsync(
        MySqlConnection conn,
        MySqlTransaction tx,
        PendingCommandEnvelope command,
        CancellationToken ct)
    {
        if (!long.TryParse(command.TargetExternalId, NumberStyles.Integer, CultureInfo.InvariantCulture, out var itemCode))
        {
            return new CommandWriteOutcome(false, "INVALID_TARGET_ID", "Invalid external item_code.", null);
        }

        var expectedStr = GetJsonString(command.Precondition, "unit_price");
        var newPriceStr = GetJsonString(command.Payload, "new_unit_price");
        if (!decimal.TryParse(expectedStr, NumberStyles.Number, CultureInfo.InvariantCulture, out var expectedPrice) ||
            !decimal.TryParse(newPriceStr, NumberStyles.Number, CultureInfo.InvariantCulture, out var newPrice) ||
            newPrice <= 0m)
        {
            return new CommandWriteOutcome(false, "INVALID_PAYLOAD", "Invalid precondition or new_unit_price.", null);
        }

        await using var selectCmd = conn.CreateCommand();
        selectCmd.Transaction = tx;
        selectCmd.CommandText = "SELECT unit_price FROM tbl_items WHERE item_code = @itemCode FOR UPDATE;";
        selectCmd.Parameters.AddWithValue("@itemCode", itemCode);
        var currentObj = await selectCmd.ExecuteScalarAsync(ct);
        if (currentObj is null or DBNull)
        {
            return new CommandWriteOutcome(false, "TARGET_NOT_FOUND", $"Item #{itemCode} not found in source.", null);
        }

        var currentPrice = Convert.ToDecimal(currentObj, CultureInfo.InvariantCulture);
        if (decimal.Round(currentPrice, 4) != decimal.Round(expectedPrice, 4))
        {
            return new CommandWriteOutcome(
                false,
                "PRECONDITION_FAILED",
                $"Optimistic concurrency check failed: expected unit_price {expectedPrice:0.0000}, actual {currentPrice:0.0000}.",
                null
            );
        }

        await using var updateCmd = conn.CreateCommand();
        updateCmd.Transaction = tx;
        updateCmd.CommandText = "UPDATE tbl_items SET unit_price = @newPrice, updated_at = NOW() WHERE item_code = @itemCode;";
        updateCmd.Parameters.AddWithValue("@newPrice", newPrice);
        updateCmd.Parameters.AddWithValue("@itemCode", itemCode);
        await updateCmd.ExecuteNonQueryAsync(ct);

        await using var verifyCmd = conn.CreateCommand();
        verifyCmd.Transaction = tx;
        verifyCmd.CommandText = "SELECT unit_price FROM tbl_items WHERE item_code = @itemCode;";
        verifyCmd.Parameters.AddWithValue("@itemCode", itemCode);
        var confirmed = Convert.ToDecimal(await verifyCmd.ExecuteScalarAsync(ct), CultureInfo.InvariantCulture);

        return new CommandWriteOutcome(true, null, null, new Dictionary<string, object?>
        {
            ["confirmed_unit_price"] = confirmed.ToString("0.0000", CultureInfo.InvariantCulture),
            ["item_code"] = itemCode,
        });
    }

    private static async Task<CommandWriteOutcome> UpdateOrderStatusAsync(
        MySqlConnection conn,
        MySqlTransaction tx,
        PendingCommandEnvelope command,
        CancellationToken ct)
    {
        if (!long.TryParse(command.TargetExternalId, NumberStyles.Integer, CultureInfo.InvariantCulture, out var invId))
        {
            return new CommandWriteOutcome(false, "INVALID_TARGET_ID", "Invalid external inv_id.", null);
        }

        var expectedStatus = GetJsonString(command.Precondition, "inv_status");
        var newStatus = GetJsonString(command.Payload, "new_inv_status");
        if (string.IsNullOrWhiteSpace(expectedStatus) || string.IsNullOrWhiteSpace(newStatus))
        {
            return new CommandWriteOutcome(false, "INVALID_PAYLOAD", "Missing inv_status precondition or new_inv_status.", null);
        }

        await using var selectCmd = conn.CreateCommand();
        selectCmd.Transaction = tx;
        selectCmd.CommandText = "SELECT inv_status FROM tbl_invoices WHERE inv_id = @invId FOR UPDATE;";
        selectCmd.Parameters.AddWithValue("@invId", invId);
        var currentObj = await selectCmd.ExecuteScalarAsync(ct);
        if (currentObj is null or DBNull)
        {
            return new CommandWriteOutcome(false, "TARGET_NOT_FOUND", $"Invoice #{invId} not found in source.", null);
        }

        var currentStatus = Convert.ToString(currentObj, CultureInfo.InvariantCulture) ?? string.Empty;
        if (!string.Equals(currentStatus.Trim(), expectedStatus.Trim(), StringComparison.OrdinalIgnoreCase))
        {
            return new CommandWriteOutcome(
                false,
                "PRECONDITION_FAILED",
                $"Optimistic concurrency check failed: expected inv_status '{expectedStatus}', actual '{currentStatus}'.",
                null
            );
        }

        await using var updateCmd = conn.CreateCommand();
        updateCmd.Transaction = tx;
        updateCmd.CommandText = "UPDATE tbl_invoices SET inv_status = @newStatus, updated_at = NOW() WHERE inv_id = @invId;";
        updateCmd.Parameters.AddWithValue("@newStatus", newStatus.Trim().ToLowerInvariant());
        updateCmd.Parameters.AddWithValue("@invId", invId);
        await updateCmd.ExecuteNonQueryAsync(ct);

        await using var verifyCmd = conn.CreateCommand();
        verifyCmd.Transaction = tx;
        verifyCmd.CommandText = "SELECT inv_status FROM tbl_invoices WHERE inv_id = @invId;";
        verifyCmd.Parameters.AddWithValue("@invId", invId);
        var confirmed = Convert.ToString(await verifyCmd.ExecuteScalarAsync(ct), CultureInfo.InvariantCulture) ?? newStatus;

        return new CommandWriteOutcome(true, null, null, new Dictionary<string, object?>
        {
            ["confirmed_inv_status"] = confirmed,
            ["inv_id"] = invId,
        });
    }

    private static async Task<CommandWriteOutcome> AdjustInventoryStockAsync(
        MySqlConnection conn,
        MySqlTransaction tx,
        PendingCommandEnvelope command,
        CancellationToken ct)
    {
        var parts = command.TargetExternalId.Split('-', StringSplitOptions.RemoveEmptyEntries);
        if (parts.Length != 2 ||
            !long.TryParse(parts[0], NumberStyles.Integer, CultureInfo.InvariantCulture, out var whId) ||
            !long.TryParse(parts[1], NumberStyles.Integer, CultureInfo.InvariantCulture, out var itemCode))
        {
            return new CommandWriteOutcome(false, "INVALID_TARGET_ID", "Invalid external stock composite ID (expected wh_id-item_code).", null);
        }

        var expectedStr = GetJsonString(command.Precondition, "qty_on_hand");
        var newQtyStr = GetJsonString(command.Payload, "new_qty_on_hand");
        if (!decimal.TryParse(expectedStr, NumberStyles.Number, CultureInfo.InvariantCulture, out var expectedQty) ||
            !decimal.TryParse(newQtyStr, NumberStyles.Number, CultureInfo.InvariantCulture, out var newQty) ||
            newQty < 0m)
        {
            return new CommandWriteOutcome(false, "INVALID_PAYLOAD", "Invalid precondition or new_qty_on_hand.", null);
        }

        await using var selectCmd = conn.CreateCommand();
        selectCmd.Transaction = tx;
        selectCmd.CommandText = "SELECT qty_on_hand FROM tbl_stock_qty WHERE wh_id = @whId AND item_code = @itemCode FOR UPDATE;";
        selectCmd.Parameters.AddWithValue("@whId", whId);
        selectCmd.Parameters.AddWithValue("@itemCode", itemCode);
        var currentObj = await selectCmd.ExecuteScalarAsync(ct);
        if (currentObj is null or DBNull)
        {
            return new CommandWriteOutcome(false, "TARGET_NOT_FOUND", $"Stock row {whId}-{itemCode} not found in source.", null);
        }

        var currentQty = Convert.ToDecimal(currentObj, CultureInfo.InvariantCulture);
        if (decimal.Round(currentQty, 4) != decimal.Round(expectedQty, 4))
        {
            return new CommandWriteOutcome(
                false,
                "PRECONDITION_FAILED",
                $"Optimistic concurrency check failed: expected qty_on_hand {expectedQty:0.0000}, actual {currentQty:0.0000}.",
                null
            );
        }

        await using var updateCmd = conn.CreateCommand();
        updateCmd.Transaction = tx;
        updateCmd.CommandText = "UPDATE tbl_stock_qty SET qty_on_hand = @newQty, last_changed_at = NOW() WHERE wh_id = @whId AND item_code = @itemCode;";
        updateCmd.Parameters.AddWithValue("@newQty", newQty);
        updateCmd.Parameters.AddWithValue("@whId", whId);
        updateCmd.Parameters.AddWithValue("@itemCode", itemCode);
        await updateCmd.ExecuteNonQueryAsync(ct);

        await using var verifyCmd = conn.CreateCommand();
        verifyCmd.Transaction = tx;
        verifyCmd.CommandText = "SELECT qty_on_hand FROM tbl_stock_qty WHERE wh_id = @whId AND item_code = @itemCode;";
        verifyCmd.Parameters.AddWithValue("@whId", whId);
        verifyCmd.Parameters.AddWithValue("@itemCode", itemCode);
        var confirmed = Convert.ToDecimal(await verifyCmd.ExecuteScalarAsync(ct), CultureInfo.InvariantCulture);

        return new CommandWriteOutcome(true, null, null, new Dictionary<string, object?>
        {
            ["confirmed_qty_on_hand"] = confirmed.ToString("0.0000", CultureInfo.InvariantCulture),
            ["wh_id"] = whId,
            ["item_code"] = itemCode,
        });
    }

    private static string? GetJsonString(JsonElement obj, string propertyName)
    {
        if (obj.ValueKind != JsonValueKind.Object || !obj.TryGetProperty(propertyName, out var prop))
        {
            return null;
        }

        return prop.ValueKind switch
        {
            JsonValueKind.String => prop.GetString(),
            JsonValueKind.Number => prop.GetRawText(),
            _ => null,
        };
    }
}
