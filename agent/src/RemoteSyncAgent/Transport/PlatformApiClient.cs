using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using System.Text.Json.Serialization;
using RemoteSyncAgent.Commands;
using RemoteSyncAgent.Storage;

namespace RemoteSyncAgent.Transport;

public sealed record EnrollResponse(
    [property: JsonPropertyName("tenant_id")] long TenantId,
    [property: JsonPropertyName("data_source_id")] long DataSourceId,
    [property: JsonPropertyName("connector_instance_id")] long ConnectorInstanceId,
    [property: JsonPropertyName("bearer_token")] string BearerToken
);

public sealed record RemoteDataSourceConfig(
    [property: JsonPropertyName("id")] long Id,
    [property: JsonPropertyName("name")] string Name,
    [property: JsonPropertyName("connector_type")] string ConnectorType,
    [property: JsonPropertyName("capture_mode")] string CaptureMode,
    [property: JsonPropertyName("sync_interval_seconds")] int SyncIntervalSeconds,
    [property: JsonPropertyName("freshness_status")] string FreshnessStatus
);

public sealed record RemoteFieldMapping(
    [property: JsonPropertyName("canonical_entity")] string CanonicalEntity,
    [property: JsonPropertyName("source_table")] string SourceTable,
    [property: JsonPropertyName("source_column")] string SourceColumn,
    [property: JsonPropertyName("canonical_field")] string CanonicalField,
    [property: JsonPropertyName("transform_rule")] string? TransformRule,
    [property: JsonPropertyName("is_required")] bool IsRequired
);

public sealed record RemoteSyncCursor(
    [property: JsonPropertyName("source_table")] string SourceTable,
    [property: JsonPropertyName("cursor_column")] string CursorColumn,
    [property: JsonPropertyName("last_cursor_value")] string LastCursorValue
);

public sealed record AgentConfigResponse(
    [property: JsonPropertyName("data_source")] RemoteDataSourceConfig DataSource,
    [property: JsonPropertyName("field_mappings")] List<RemoteFieldMapping> FieldMappings,
    [property: JsonPropertyName("sync_cursors")] List<RemoteSyncCursor> SyncCursors,
    [property: JsonPropertyName("command_signing_public_key_ed25519")] string? CommandSigningPublicKeyEd25519 = null,
    [property: JsonPropertyName("signing_key_id")] string? SigningKeyId = null,
    [property: JsonPropertyName("allowed_write_commands")] List<string>? AllowedWriteCommands = null
);

public sealed record PendingCommandsResponse(
    [property: JsonPropertyName("data_source_id")] long DataSourceId,
    [property: JsonPropertyName("commands")] List<PendingCommandEnvelope> Commands
);

public sealed record SyncBatchResponse(
    [property: JsonPropertyName("sync_run_id")] long SyncRunId,
    [property: JsonPropertyName("status")] string Status,
    [property: JsonPropertyName("records_received")] int RecordsReceived,
    [property: JsonPropertyName("records_upserted")] int RecordsUpserted,
    [property: JsonPropertyName("records_skipped_duplicate")] int RecordsSkippedDuplicate,
    [property: JsonPropertyName("records_failed")] int RecordsFailed,
    [property: JsonPropertyName("freshness_status")] string FreshnessStatus,
    [property: JsonPropertyName("idempotent_replay")] bool IdempotentReplay
);

public interface IPlatformApiClient
{
    Task<EnrollResponse> EnrollAsync(
        string apiBaseUrl,
        string enrollmentCode,
        string deviceId,
        string deviceName,
        string agentVersion,
        CancellationToken ct);

    Task<AgentConfigResponse?> FetchConfigAsync(string apiBaseUrl, string bearerToken, CancellationToken ct);

    Task<(bool Success, int StatusCode, SyncBatchResponse? Body, string? Error)> SendSyncBatchAsync(
        string apiBaseUrl,
        string bearerToken,
        string idempotencyKey,
        string runType,
        IReadOnlyList<OutboxEventRow> events,
        CancellationToken ct);

    Task<bool> SendHeartbeatAsync(
        string apiBaseUrl,
        string bearerToken,
        string agentVersion,
        int outboxQueueDepth,
        string lastSyncStatus,
        CancellationToken ct);

    Task<List<PendingCommandEnvelope>> FetchPendingCommandsAsync(
        string apiBaseUrl,
        string bearerToken,
        CancellationToken ct);

    Task<bool> AcknowledgeCommandAsync(
        string apiBaseUrl,
        string bearerToken,
        long commandId,
        string status,
        Dictionary<string, object?>? result,
        string? errorCode,
        string? errorMessage,
        CancellationToken ct);
}

public sealed class PlatformApiClient : IPlatformApiClient
{
    private readonly HttpClient _http;

    public PlatformApiClient(HttpClient http)
    {
        _http = http;
    }

    public async Task<EnrollResponse> EnrollAsync(
        string apiBaseUrl,
        string enrollmentCode,
        string deviceId,
        string deviceName,
        string agentVersion,
        CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/enroll";
        using var response = await _http.PostAsJsonAsync(url, new
        {
            enrollment_code = enrollmentCode.Trim(),
            device_id = deviceId.Trim(),
            device_name = deviceName.Trim(),
            agent_version = agentVersion.Trim(),
        }, ct);

        if (!response.IsSuccessStatusCode)
        {
            var errText = await response.Content.ReadAsStringAsync(ct);
            throw new InvalidOperationException($"Agent enrollment failed ({(int)response.StatusCode}): {errText}");
        }

        var result = await response.Content.ReadFromJsonAsync<EnrollResponse>(cancellationToken: ct);
        return result ?? throw new InvalidOperationException("Empty enrollment response from platform API.");
    }

    public async Task<AgentConfigResponse?> FetchConfigAsync(string apiBaseUrl, string bearerToken, CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/config";
        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

        using var response = await _http.SendAsync(req, ct);
        if (!response.IsSuccessStatusCode)
        {
            return null;
        }

        return await response.Content.ReadFromJsonAsync<AgentConfigResponse>(cancellationToken: ct);
    }

    public async Task<(bool Success, int StatusCode, SyncBatchResponse? Body, string? Error)> SendSyncBatchAsync(
        string apiBaseUrl,
        string bearerToken,
        string idempotencyKey,
        string runType,
        IReadOnlyList<OutboxEventRow> events,
        CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/sync/batches";
        var wireEvents = events.Select(e => new
        {
            event_id = e.EventId,
            entity_type = e.EntityType,
            external_id = e.ExternalId,
            payload = JsonSerializer.Deserialize<JsonElement>(e.PayloadJson),
            cursor_table = e.CursorTable,
            cursor_column = e.CursorColumn,
            cursor_value = e.CursorValue,
        }).ToList();

        using var req = new HttpRequestMessage(HttpMethod.Post, url);
        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        req.Headers.TryAddWithoutValidation("Idempotency-Key", idempotencyKey);
        req.Content = JsonContent.Create(new
        {
            run_type = runType,
            @async = false,
            events = wireEvents,
        });

        try
        {
            using var response = await _http.SendAsync(req, ct);
            var statusCode = (int)response.StatusCode;
            if (response.IsSuccessStatusCode)
            {
                var body = await response.Content.ReadFromJsonAsync<SyncBatchResponse>(cancellationToken: ct);
                return (true, statusCode, body, null);
            }

            var errBody = await response.Content.ReadAsStringAsync(ct);
            return (false, statusCode, null, $"HTTP {statusCode}: {errBody}");
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException)
        {
            return (false, (int)HttpStatusCode.ServiceUnavailable, null, ex.Message);
        }
    }

    public async Task<bool> SendHeartbeatAsync(
        string apiBaseUrl,
        string bearerToken,
        string agentVersion,
        int outboxQueueDepth,
        string lastSyncStatus,
        CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/heartbeat";
        using var req = new HttpRequestMessage(HttpMethod.Post, url);
        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        req.Content = JsonContent.Create(new
        {
            agent_version = agentVersion,
            outbox_queue_depth = outboxQueueDepth,
            last_sync_status = lastSyncStatus,
        });

        try
        {
            using var response = await _http.SendAsync(req, ct);
            return response.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }

    public async Task<List<PendingCommandEnvelope>> FetchPendingCommandsAsync(
        string apiBaseUrl,
        string bearerToken,
        CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/commands/pending";
        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

        try
        {
            using var response = await _http.SendAsync(req, ct);
            if (!response.IsSuccessStatusCode)
            {
                return [];
            }

            var payload = await response.Content.ReadFromJsonAsync<PendingCommandsResponse>(cancellationToken: ct);
            return payload?.Commands ?? [];
        }
        catch
        {
            return [];
        }
    }

    public async Task<bool> AcknowledgeCommandAsync(
        string apiBaseUrl,
        string bearerToken,
        long commandId,
        string status,
        Dictionary<string, object?>? result,
        string? errorCode,
        string? errorMessage,
        CancellationToken ct)
    {
        var url = $"{apiBaseUrl.TrimEnd('/')}/api/v1/agent/commands/{commandId}/ack";
        using var req = new HttpRequestMessage(HttpMethod.Post, url);
        req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", bearerToken);
        req.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
        req.Content = JsonContent.Create(new
        {
            status,
            result,
            error_code = errorCode,
            error_message = errorMessage,
        });

        try
        {
            using var response = await _http.SendAsync(req, ct);
            return response.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }
}
