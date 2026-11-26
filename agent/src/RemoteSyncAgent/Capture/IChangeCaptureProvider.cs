using RemoteSyncAgent.Storage;

namespace RemoteSyncAgent.Capture;

public sealed record CaptureResult(
    IReadOnlyList<CapturedEvent> Events,
    IReadOnlyList<SyncCheckpoint> UpdatedCheckpoints
);

public interface IChangeCaptureProvider
{
    string StrategyName { get; }

    Task<CaptureResult> CaptureIncrementalAsync(
        string mysqlConnectionString,
        IReadOnlySet<string> allowedTables,
        SqliteOutboxStore store,
        CancellationToken cancellationToken);
}
