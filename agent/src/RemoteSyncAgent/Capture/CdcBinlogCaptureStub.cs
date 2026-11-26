using RemoteSyncAgent.Storage;

namespace RemoteSyncAgent.Capture;

/// <summary>
/// Opt-in future capture provider stub for MySQL/MariaDB Binary Log (CDC).
/// Phase 05B defaults strictly to IncrementalPollingCaptureProvider per ADR-009.
/// </summary>
public sealed class CdcBinlogCaptureStub : IChangeCaptureProvider
{
    public string StrategyName => "cdc_binlog";

    public Task<CaptureResult> CaptureIncrementalAsync(
        string mysqlConnectionString,
        IReadOnlySet<string> allowedTables,
        SqliteOutboxStore store,
        CancellationToken cancellationToken)
    {
        throw new NotSupportedException(
            "CDC binlog capture is reserved for future opt-in releases. Use incremental_polling in Phase 05B."
        );
    }
}
