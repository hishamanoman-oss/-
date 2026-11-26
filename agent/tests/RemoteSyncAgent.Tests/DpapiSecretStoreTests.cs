using System.Security.Cryptography;
using RemoteSyncAgent.Security;

namespace RemoteSyncAgent.Tests;

public sealed class DpapiSecretStoreTests : IDisposable
{
    private readonly string _tempDir = Path.Combine(Path.GetTempPath(), $"rbd-dpapi-test-{Guid.NewGuid():N}");

    public void Dispose()
    {
        if (Directory.Exists(_tempDir))
        {
            Directory.Delete(_tempDir, recursive: true);
        }
    }

    [Fact]
    public void Save_and_Load_roundtrip_encrypts_secrets_via_Windows_DPAPI_without_plaintext_on_disk()
    {
        var store = new DpapiSecretStore();
        var secretsFile = Path.Combine(_tempDir, "agent.secrets.dpapi");
        const string rawToken = "super_secret_bearer_token_987654321";
        const string rawPassword = "MockPosReaderPassword!2026";
        var payload = new AgentSecretsPayload(
            ApiBaseUrl: "http://127.0.0.1:8000",
            BearerToken: rawToken,
            MysqlConnectionString: $"Server=127.0.0.1;Port=3307;Database=mock_pos_db;Uid=mock_pos_reader;Pwd={rawPassword};",
            TenantId: 1,
            DataSourceId: 1,
            ConnectorInstanceId: 9,
            DeviceId: "TEST-WIN-01",
            DeviceName: "Test Store Terminal",
            ScopeName: "CurrentUser"
        );

        store.Save(secretsFile, payload, DataProtectionScope.CurrentUser);

        Assert.True(File.Exists(secretsFile));
        var rawDiskText = File.ReadAllText(secretsFile);
        Assert.DoesNotContain(rawToken, rawDiskText, StringComparison.Ordinal);
        Assert.DoesNotContain(rawPassword, rawDiskText, StringComparison.Ordinal);
        Assert.DoesNotContain("mock_pos_reader", rawDiskText, StringComparison.Ordinal);

        var loaded = store.Load(secretsFile, DataProtectionScope.CurrentUser);
        Assert.NotNull(loaded);
        Assert.Equal(rawToken, loaded.BearerToken);
        Assert.Contains(rawPassword, loaded.MysqlConnectionString, StringComparison.Ordinal);
        Assert.Equal("TEST-WIN-01", loaded.DeviceId);
    }

    [Fact]
    public void SecretRedactor_strips_bearer_tokens_and_mysql_passwords_from_log_strings()
    {
        const string raw = "Failed connecting with Bearer abc123SECRETtoken and Server=127.0.0.1;Pwd=MyTopSecret!99;Uid=reader";
        var redacted = SecretRedactor.Redact(raw);

        Assert.DoesNotContain("abc123SECRETtoken", redacted, StringComparison.Ordinal);
        Assert.DoesNotContain("MyTopSecret!99", redacted, StringComparison.Ordinal);
        Assert.Contains("[REDACTED]", redacted, StringComparison.Ordinal);
    }
}
