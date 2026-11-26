using RemoteSyncAgent.Capture;
using RemoteSyncAgent.Storage;

namespace RemoteSyncAgent.Tests;

public sealed class IncrementalPollingCaptureTests : IDisposable
{
    private readonly string _tempDir = Path.Combine(Path.GetTempPath(), $"rbd-capture-test-{Guid.NewGuid():N}");

    public void Dispose()
    {
        if (Directory.Exists(_tempDir))
        {
            Directory.Delete(_tempDir, recursive: true);
        }
    }

    [Fact]
    public void Allow_list_enforces_strict_readonly_POS_tables_and_rejects_arbitrary_tables()
    {
        Assert.True(IncrementalPollingCaptureProvider.IsAllowedTable("tbl_branches"));
        Assert.True(IncrementalPollingCaptureProvider.IsAllowedTable("tbl_invoices"));
        Assert.True(IncrementalPollingCaptureProvider.IsAllowedTable("tbl_invoice_lines"));

        Assert.False(IncrementalPollingCaptureProvider.IsAllowedTable("users"));
        Assert.False(IncrementalPollingCaptureProvider.IsAllowedTable("tbl_invoices; DROP TABLE tbl_items;--"));
    }

    [Fact]
    public async Task Checkpoints_persist_cursor_advancement_and_CdcBinlogStub_fails_explicitly()
    {
        var dbPath = Path.Combine(_tempDir, "cp.sqlite");
        var store = new SqliteOutboxStore(dbPath);
        store.EnsureInitialized();

        Assert.Null(store.GetCheckpoint("tbl_invoices"));
        store.UpsertCheckpoint("tbl_invoices", "updated_at", "2026-11-27 01:00:00", 28);

        var cp = store.GetCheckpoint("tbl_invoices");
        Assert.NotNull(cp);
        Assert.Equal("updated_at", cp.CursorColumn);
        Assert.Equal("2026-11-27 01:00:00", cp.LastCursorValue);
        Assert.Equal(28, cp.LastPk);

        var stub = new CdcBinlogCaptureStub();
        await Assert.ThrowsAsync<NotSupportedException>(() =>
            stub.CaptureIncrementalAsync("Server=x;", IncrementalPollingCaptureProvider.DefaultAllowedTables, store, CancellationToken.None));
    }
}
