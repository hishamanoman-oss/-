using Microsoft.Extensions.Logging.Abstractions;
using Microsoft.Extensions.Options;
using RemoteSyncAgent.Capture;
using RemoteSyncAgent.Configuration;
using RemoteSyncAgent.Security;
using RemoteSyncAgent.Storage;
using RemoteSyncAgent.Transport;
using RemoteSyncAgent.Workers;

namespace RemoteSyncAgent.Tests;

public sealed class SqliteOutboxAndRetryTests : IDisposable
{
    private readonly string _tempDir = Path.Combine(Path.GetTempPath(), $"rbd-outbox-test-{Guid.NewGuid():N}");

    public void Dispose()
    {
        if (Directory.Exists(_tempDir))
        {
            Directory.Delete(_tempDir, recursive: true);
        }
    }

    [Fact]
    public void Outbox_persists_across_restart_deduplicates_events_and_computes_deterministic_idempotency_keys()
    {
        var dbPath = Path.Combine(_tempDir, "agent-outbox.sqlite");
        var ev1 = IncrementalPollingCaptureProvider.BuildEvent(
            "order",
            "501",
            new Dictionary<string, object?> { ["inv_id"] = 501, ["inv_number"] = "POS-TEST-501" },
            "tbl_invoices",
            "updated_at",
            "2026-11-27 00:00:00");

        var store1 = new SqliteOutboxStore(dbPath);
        store1.EnsureInitialized();
        var insertedFirst = store1.EnqueueEvents([ev1]);
        var insertedDup = store1.EnqueueEvents([ev1]);

        Assert.Equal(1, insertedFirst);
        Assert.Equal(0, insertedDup);

        // Simulate full agent process restart using a brand new SqliteOutboxStore instance
        var storeAfterRestart = new SqliteOutboxStore(dbPath);
        storeAfterRestart.EnsureInitialized();
        Assert.Equal(1, storeAfterRestart.GetPendingCount());

        var pending = storeAfterRestart.GetDuePendingEvents(10, DateTimeOffset.UtcNow.AddSeconds(1));
        Assert.Single(pending);
        var key1 = SqliteOutboxStore.ComputeBatchIdempotencyKey(pending);
        var key2 = SqliteOutboxStore.ComputeBatchIdempotencyKey(pending);
        Assert.Equal(key1, key2);
    }

    [Fact]
    public void Exponential_backoff_caps_at_max_and_retried_events_flush_on_api_recovery()
    {
        Assert.Equal(2, SqliteOutboxStore.CalculateExponentialBackoffSeconds(1, 2, 60));
        Assert.Equal(4, SqliteOutboxStore.CalculateExponentialBackoffSeconds(2, 2, 60));
        Assert.Equal(8, SqliteOutboxStore.CalculateExponentialBackoffSeconds(3, 2, 60));
        Assert.Equal(60, SqliteOutboxStore.CalculateExponentialBackoffSeconds(10, 2, 60));

        var dbPath = Path.Combine(_tempDir, "agent-retry.sqlite");
        var store = new SqliteOutboxStore(dbPath);
        store.EnsureInitialized();

        var ev = IncrementalPollingCaptureProvider.BuildEvent(
            "order",
            "777",
            new Dictionary<string, object?> { ["inv_id"] = 777, ["inv_number"] = "POS-RETRY-777" },
            "tbl_invoices",
            "updated_at",
            "2026-11-27 00:01:00");
        store.EnqueueEvents([ev]);

        var ids = store.GetDuePendingEvents(10, DateTimeOffset.UtcNow.AddSeconds(1)).Select(x => x.Id).ToList();
        var now = DateTimeOffset.UtcNow;
        var delay = store.RecordRetryFailure(ids, "Connection refused", initialBackoffSeconds: 2, maxBackoffSeconds: 60, now: now);

        Assert.Equal(TimeSpan.FromSeconds(2), delay);
        Assert.Equal(1, store.GetPendingCount());
        // Not yet due immediately
        Assert.Empty(store.GetDuePendingEvents(10, now));
        // Due after backoff elapses
        var dueLater = store.GetDuePendingEvents(10, now.AddSeconds(3));
        Assert.Single(dueLater);

        store.MarkDelivered(dueLater.Select(x => x.Id).ToList(), now.AddSeconds(3));
        Assert.Equal(0, store.GetPendingCount());
        Assert.Equal(1, store.GetDeliveredCount());
    }

    [Fact]
    public async Task Worker_retains_events_while_API_is_offline_then_delivers_after_restart_without_duplicates()
    {
        var dbPath = Path.Combine(_tempDir, "worker-outage.sqlite");
        var store = new SqliteOutboxStore(dbPath);
        store.EnsureInitialized();

        var ev = IncrementalPollingCaptureProvider.BuildEvent(
            "order",
            "888",
            new Dictionary<string, object?> { ["inv_id"] = 888, ["inv_number"] = "POS-OUTAGE-888" },
            "tbl_invoices",
            "updated_at",
            "2026-11-27 00:02:00");

        var stubCapture = new FakeCaptureProvider([ev]);
        var offlineApi = new FakeApiClient { IsOnline = false };
        var secrets = new AgentSecretsPayload("http://127.0.0.1:8000", "tok", "Server=mock;", 1, 1, 1, "D1", "Dev", "CurrentUser");
        var opts = Options.Create(new AgentOptions { InitialBackoffSeconds = 1, MaxBackoffSeconds = 10, MaxBatchSize = 50 });

        var workerOffline = new SyncWorkerService(
            NullLogger<SyncWorkerService>.Instance,
            opts,
            new StubSecretStore(secrets),
            stubCapture,
            offlineApi);

        await workerOffline.RunSingleCycleAsync(secrets, store, CancellationToken.None);

        // Must stay queued in SQLite because API was offline
        Assert.Equal(1, store.GetPendingCount());
        Assert.Equal(0, store.GetDeliveredCount());
        Assert.Equal("backoff_retry", store.GetState("last_sync_status"));

        // Simulate restart with fresh SQLite store + online API
        var restartedStore = new SqliteOutboxStore(dbPath);
        restartedStore.EnsureInitialized();
        var onlineApi = new FakeApiClient { IsOnline = true };
        var emptyCapture = new FakeCaptureProvider([]);

        // Fast-forward next_attempt_at by waiting 1.1s for backoff to expire cleanly
        await Task.Delay(1100);

        var workerOnline = new SyncWorkerService(
            NullLogger<SyncWorkerService>.Instance,
            opts,
            new StubSecretStore(secrets),
            emptyCapture,
            onlineApi);

        await workerOnline.RunSingleCycleAsync(secrets, restartedStore, CancellationToken.None);

        Assert.Equal(0, restartedStore.GetPendingCount());
        Assert.Equal(1, restartedStore.GetDeliveredCount());
        Assert.Single(onlineApi.DeliveredKeys);
    }

    private sealed class FakeCaptureProvider(IReadOnlyList<CapturedEvent> eventsToReturn) : IChangeCaptureProvider
    {
        public string StrategyName => "fake";

        public Task<CaptureResult> CaptureIncrementalAsync(string mysqlConnectionString, IReadOnlySet<string> allowedTables, SqliteOutboxStore store, CancellationToken cancellationToken)
            => Task.FromResult(new CaptureResult(eventsToReturn, []));
    }

    private sealed class StubSecretStore(AgentSecretsPayload payload) : ISecretStore
    {
        public void Save(string filePath, AgentSecretsPayload p, System.Security.Cryptography.DataProtectionScope scope) { }
        public AgentSecretsPayload? Load(string filePath, System.Security.Cryptography.DataProtectionScope scope) => payload;
    }

    private sealed class FakeApiClient : IPlatformApiClient
    {
        public bool IsOnline { get; set; }
        public List<string> DeliveredKeys { get; } = [];

        public Task<EnrollResponse> EnrollAsync(string apiBaseUrl, string enrollmentCode, string deviceId, string deviceName, string agentVersion, CancellationToken ct)
            => throw new NotImplementedException();

        public Task<AgentConfigResponse?> FetchConfigAsync(string apiBaseUrl, string bearerToken, CancellationToken ct)
            => Task.FromResult<AgentConfigResponse?>(null);

        public Task<(bool Success, int StatusCode, SyncBatchResponse? Body, string? Error)> SendSyncBatchAsync(
            string apiBaseUrl, string bearerToken, string idempotencyKey, string runType, IReadOnlyList<OutboxEventRow> events, CancellationToken ct)
        {
            if (!IsOnline)
            {
                return Task.FromResult<(bool, int, SyncBatchResponse?, string?)>((false, 503, null, "API unreachable"));
            }

            DeliveredKeys.Add(idempotencyKey);
            return Task.FromResult<(bool, int, SyncBatchResponse?, string?)>(
                (true, 200, new SyncBatchResponse(1, "succeeded", events.Count, events.Count, 0, 0, "live", false), null));
        }

        public Task<bool> SendHeartbeatAsync(string apiBaseUrl, string bearerToken, string agentVersion, int outboxQueueDepth, string lastSyncStatus, CancellationToken ct)
            => Task.FromResult(IsOnline);
    }
}
