using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using RemoteSyncAgent.Capture;
using RemoteSyncAgent.Configuration;
using RemoteSyncAgent.Security;
using RemoteSyncAgent.Storage;
using RemoteSyncAgent.Transport;

namespace RemoteSyncAgent.Workers;

public sealed class SyncWorkerService : BackgroundService
{
    private readonly ILogger<SyncWorkerService> _logger;
    private readonly AgentOptions _options;
    private readonly ISecretStore _secretStore;
    private readonly IChangeCaptureProvider _captureProvider;
    private readonly IPlatformApiClient _apiClient;

    public SyncWorkerService(
        ILogger<SyncWorkerService> logger,
        IOptions<AgentOptions> options,
        ISecretStore secretStore,
        IChangeCaptureProvider captureProvider,
        IPlatformApiClient apiClient)
    {
        _logger = logger;
        _options = options.Value;
        _secretStore = secretStore;
        _captureProvider = captureProvider;
        _apiClient = apiClient;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        var dataDir = _options.ResolveDataDirectory();
        var secretsPath = Path.Combine(dataDir, "agent.secrets.dpapi");
        var sqlitePath = Path.Combine(dataDir, "agent-outbox.sqlite");

        var store = new SqliteOutboxStore(sqlitePath);
        store.EnsureInitialized();

        _logger.LogInformation("RemoteSyncAgent started (strategy={Strategy}, dataDir={DataDir}, inboundPorts=0).", _captureProvider.StrategyName, dataDir);

        while (!stoppingToken.IsCancellationRequested)
        {
            var intervalSec = Math.Max(1, _options.PollIntervalSeconds);
            try
            {
                var secrets = _secretStore.Load(secretsPath, _options.ResolveProtectionScope());
                if (secrets is null)
                {
                    _logger.LogWarning("No enrolled secrets found at {Path}. Run CLI `enroll` command first.", secretsPath);
                }
                else
                {
                    await RunSingleCycleAsync(secrets, store, stoppingToken);
                }
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError("Sync cycle error: {Message}", SecretRedactor.Redact(ex.Message));
            }

            try
            {
                await Task.Delay(TimeSpan.FromSeconds(intervalSec), stoppingToken);
            }
            catch (OperationCanceledException)
            {
                break;
            }
        }

        _logger.LogInformation("RemoteSyncAgent shutting down gracefully.");
    }

    public async Task RunSingleCycleAsync(
        AgentSecretsPayload secrets,
        SqliteOutboxStore store,
        CancellationToken ct)
    {
        // 1. Attempt to fetch active config & field mappings (non-fatal if API is temporarily offline)
        IReadOnlySet<string> allowedTables = IncrementalPollingCaptureProvider.DefaultAllowedTables;
        try
        {
            var cfg = await _apiClient.FetchConfigAsync(secrets.ApiBaseUrl, secrets.BearerToken, ct);
            if (cfg is not null)
            {
                var mappedTables = cfg.FieldMappings
                    .Select(m => m.SourceTable)
                    .Where(IncrementalPollingCaptureProvider.IsAllowedTable)
                    .ToHashSet(StringComparer.OrdinalIgnoreCase);

                if (mappedTables.Count > 0)
                {
                    mappedTables.Add("tbl_invoice_lines");
                    allowedTables = mappedTables;
                }
            }
        }
        catch (Exception ex) when (ex is not OperationCanceledException)
        {
            _logger.LogWarning("Unable to refresh remote config (will continue with local checkpoints & outbox): {Err}", SecretRedactor.Redact(ex.Message));
        }

        // 2. Capture incremental changes from local read-only MySQL/MariaDB into durable SQLite outbox
        try
        {
            var capture = await _captureProvider.CaptureIncrementalAsync(
                secrets.MysqlConnectionString,
                allowedTables,
                store,
                ct);

            if (capture.Events.Count > 0)
            {
                var inserted = store.EnqueueEvents(capture.Events);
                foreach (var cp in capture.UpdatedCheckpoints)
                {
                    store.UpsertCheckpoint(cp.SourceTable, cp.CursorColumn, cp.LastCursorValue, cp.LastPk);
                }

                _logger.LogInformation("Captured {Captured} events ({Inserted} new queued in SQLite outbox).", capture.Events.Count, inserted);
            }
        }
        catch (Exception ex) when (ex is not OperationCanceledException)
        {
            _logger.LogError("Local MySQL read-only capture failed: {Err}", SecretRedactor.Redact(ex.Message));
        }

        // 3. Flush due outbox batches to Platform API with deterministic Idempotency-Key & exponential backoff
        var lastSyncStatus = store.GetState("last_sync_status") ?? "idle";
        while (!ct.IsCancellationRequested)
        {
            var batch = store.GetDuePendingEvents(_options.MaxBatchSize);
            if (batch.Count == 0)
            {
                break;
            }

            var idempotencyKey = SqliteOutboxStore.ComputeBatchIdempotencyKey(batch);
            var ids = batch.Select(x => x.Id).ToList();

            var (success, statusCode, body, err) = await _apiClient.SendSyncBatchAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                idempotencyKey,
                runType: "incremental",
                events: batch,
                ct: ct);

            if (success)
            {
                store.MarkDelivered(ids);
                lastSyncStatus = body?.Status ?? "succeeded";
                store.SetState("last_sync_status", lastSyncStatus);
                _logger.LogInformation(
                    "Delivered {Count} outbox events (syncRunId={RunId}, upserted={Upserted}, skippedDup={Skipped}).",
                    ids.Count,
                    body?.SyncRunId ?? 0,
                    body?.RecordsUpserted ?? 0,
                    body?.RecordsSkippedDuplicate ?? 0);
            }
            else
            {
                var delay = store.RecordRetryFailure(
                    ids,
                    err ?? $"HTTP {statusCode}",
                    _options.InitialBackoffSeconds,
                    _options.MaxBackoffSeconds);
                lastSyncStatus = "backoff_retry";
                store.SetState("last_sync_status", lastSyncStatus);
                _logger.LogWarning(
                    "Platform ingestion failed ({StatusCode}); {Count} events retained in SQLite outbox with backoff {DelaySec}s: {Err}",
                    statusCode,
                    ids.Count,
                    (int)delay.TotalSeconds,
                    SecretRedactor.Redact(err));
                break;
            }
        }

        // 4. Outbound heartbeat
        var queueDepth = store.GetPendingCount();
        await _apiClient.SendHeartbeatAsync(
            secrets.ApiBaseUrl,
            secrets.BearerToken,
            _options.AgentVersion,
            queueDepth,
            lastSyncStatus,
            ct);
    }
}
