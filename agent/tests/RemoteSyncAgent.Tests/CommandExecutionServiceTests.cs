using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using Microsoft.Extensions.Logging.Abstractions;
using Org.BouncyCastle.Crypto.Generators;
using Org.BouncyCastle.Crypto.Parameters;
using Org.BouncyCastle.Crypto.Signers;
using Org.BouncyCastle.Security;
using RemoteSyncAgent.Commands;
using RemoteSyncAgent.Security;
using RemoteSyncAgent.Storage;
using RemoteSyncAgent.Transport;

namespace RemoteSyncAgent.Tests;

public sealed class CommandExecutionServiceTests : IDisposable
{
    private readonly string _tempDir = Path.Combine(Path.GetTempPath(), $"rbd-cmd-test-{Guid.NewGuid():N}");

    public void Dispose()
    {
        if (Directory.Exists(_tempDir))
        {
            Directory.Delete(_tempDir, recursive: true);
        }
    }

    [Fact]
    public async Task Valid_Ed25519_signed_command_executes_and_subsequent_replay_is_rejected()
    {
        var (pubB64, privKey) = GenerateEd25519Pair();
        var store = CreateStore();
        var writer = new RecordingTargetWriter(new CommandWriteOutcome(true, null, null, new Dictionary<string, object?> { ["confirmed_unit_price"] = "550.0000" }));
        var api = new RecordingApiClient();
        var executor = new CommandExecutionService(NullLogger<CommandExecutionService>.Instance, writer, api);

        var env = CreateSignedEnvelope(
            commandId: 101,
            commandType: "product.price.update",
            targetExternalId: "1",
            payloadJson: "{\"new_unit_price\":\"550.0000\",\"reason\":\"Approved margin adjustment\"}",
            preconditionJson: "{\"unit_price\":\"480.0000\"}",
            idempotencyKey: "idem-cmd-101",
            expiresAtUtc: DateTimeOffset.UtcNow.AddMinutes(20).ToString("yyyy-MM-ddTHH:mm:ssZ"),
            privKey: privKey
        );

        var secrets = CreateSecrets(pubB64);

        // 1. Valid execution
        var res = await executor.ExecuteSingleAsync(secrets, store, pubB64, env, CancellationToken.None);
        Assert.True(res.Success);
        Assert.Equal(1, writer.InvocationCount);
        Assert.Contains(api.Acks, a => a.CommandId == 101 && a.Status == "executing");
        Assert.Contains(api.Acks, a => a.CommandId == 101 && a.Status == "succeeded");
        Assert.True(store.HasExecutedCommand(101, "idem-cmd-101"));

        // 2. Replay attempt with same command_id / idempotency_key must be rejected cleanly without hitting MySQL writer
        var replay = await executor.ExecuteSingleAsync(secrets, store, pubB64, env, CancellationToken.None);
        Assert.False(replay.Success);
        Assert.Equal("REPLAY_REJECTED", replay.ErrorCode);
        Assert.Equal(1, writer.InvocationCount);
        Assert.Contains(api.Acks, a => a.CommandId == 101 && a.Status == "failed" && a.ErrorCode == "REPLAY_REJECTED");
    }

    [Fact]
    public async Task Tampered_payload_or_invalid_Ed25519_signature_is_rejected()
    {
        var (pubB64, privKey) = GenerateEd25519Pair();
        var store = CreateStore();
        var writer = new RecordingTargetWriter(new CommandWriteOutcome(true, null, null, null));
        var api = new RecordingApiClient();
        var executor = new CommandExecutionService(NullLogger<CommandExecutionService>.Instance, writer, api);

        var valid = CreateSignedEnvelope(
            commandId: 102,
            commandType: "product.price.update",
            targetExternalId: "1",
            payloadJson: "{\"new_unit_price\":\"500.0000\"}",
            preconditionJson: "{\"unit_price\":\"480.0000\"}",
            idempotencyKey: "idem-cmd-102",
            expiresAtUtc: DateTimeOffset.UtcNow.AddMinutes(20).ToString("yyyy-MM-ddTHH:mm:ssZ"),
            privKey: privKey
        );

        // Tamper payload after signing
        var tampered = valid with
        {
            Payload = JsonDocument.Parse("{\"new_unit_price\":\"1.0000\"}").RootElement.Clone(),
        };

        var outcome = await executor.ExecuteSingleAsync(CreateSecrets(pubB64), store, pubB64, tampered, CancellationToken.None);
        Assert.False(outcome.Success);
        Assert.Equal("INVALID_SIGNATURE", outcome.ErrorCode);
        Assert.Equal(0, writer.InvocationCount);
        Assert.Contains(api.Acks, a => a.CommandId == 102 && a.ErrorCode == "INVALID_SIGNATURE");
    }

    [Fact]
    public async Task Expired_command_is_rejected_before_touching_database()
    {
        var (pubB64, privKey) = GenerateEd25519Pair();
        var store = CreateStore();
        var writer = new RecordingTargetWriter(new CommandWriteOutcome(true, null, null, null));
        var api = new RecordingApiClient();
        var executor = new CommandExecutionService(NullLogger<CommandExecutionService>.Instance, writer, api);

        var expiredEnv = CreateSignedEnvelope(
            commandId: 103,
            commandType: "product.price.update",
            targetExternalId: "1",
            payloadJson: "{\"new_unit_price\":\"500.0000\"}",
            preconditionJson: "{\"unit_price\":\"480.0000\"}",
            idempotencyKey: "idem-cmd-103",
            expiresAtUtc: DateTimeOffset.UtcNow.AddMinutes(-5).ToString("yyyy-MM-ddTHH:mm:ssZ"),
            privKey: privKey
        );

        var outcome = await executor.ExecuteSingleAsync(CreateSecrets(pubB64), store, pubB64, expiredEnv, CancellationToken.None);
        Assert.False(outcome.Success);
        Assert.Equal("COMMAND_EXPIRED", outcome.ErrorCode);
        Assert.Equal(0, writer.InvocationCount);
    }

    [Fact]
    public async Task Unsupported_or_arbitrary_SQL_command_type_is_rejected()
    {
        var (pubB64, privKey) = GenerateEd25519Pair();
        var store = CreateStore();
        var writer = new RecordingTargetWriter(new CommandWriteOutcome(true, null, null, null));
        var api = new RecordingApiClient();
        var executor = new CommandExecutionService(NullLogger<CommandExecutionService>.Instance, writer, api);

        var rogueEnv = CreateSignedEnvelope(
            commandId: 104,
            commandType: "sql.arbitrary.exec; DROP TABLE tbl_items;--",
            targetExternalId: "1",
            payloadJson: "{\"sql\":\"DROP TABLE tbl_items\"}",
            preconditionJson: "{}",
            idempotencyKey: "idem-cmd-104",
            expiresAtUtc: DateTimeOffset.UtcNow.AddMinutes(10).ToString("yyyy-MM-ddTHH:mm:ssZ"),
            privKey: privKey
        );

        var outcome = await executor.ExecuteSingleAsync(CreateSecrets(pubB64), store, pubB64, rogueEnv, CancellationToken.None);
        Assert.False(outcome.Success);
        Assert.Equal("UNSUPPORTED_COMMAND", outcome.ErrorCode);
        Assert.Equal(0, writer.InvocationCount);
    }

    [Fact]
    public async Task Precondition_mismatch_fails_cleanly_with_PRECONDITION_FAILED()
    {
        var (pubB64, privKey) = GenerateEd25519Pair();
        var store = CreateStore();
        var writer = new RecordingTargetWriter(new CommandWriteOutcome(false, "PRECONDITION_FAILED", "Actual price changed concurrently", null));
        var api = new RecordingApiClient();
        var executor = new CommandExecutionService(NullLogger<CommandExecutionService>.Instance, writer, api);

        var env = CreateSignedEnvelope(
            commandId: 105,
            commandType: "product.price.update",
            targetExternalId: "1",
            payloadJson: "{\"new_unit_price\":\"520.0000\"}",
            preconditionJson: "{\"unit_price\":\"480.0000\"}",
            idempotencyKey: "idem-cmd-105",
            expiresAtUtc: DateTimeOffset.UtcNow.AddMinutes(10).ToString("yyyy-MM-ddTHH:mm:ssZ"),
            privKey: privKey
        );

        var outcome = await executor.ExecuteSingleAsync(CreateSecrets(pubB64), store, pubB64, env, CancellationToken.None);
        Assert.False(outcome.Success);
        Assert.Equal("PRECONDITION_FAILED", outcome.ErrorCode);
        Assert.False(store.HasExecutedCommand(105, "idem-cmd-105"));
        Assert.Contains(api.Acks, a => a.CommandId == 105 && a.Status == "failed" && a.ErrorCode == "PRECONDITION_FAILED");
    }

    [Fact]
    public void DPAPI_encrypts_separate_write_credentials_without_plaintext_leakage()
    {
        var store = new DpapiSecretStore();
        var file = Path.Combine(_tempDir, "write-secrets.dpapi");
        const string writePwd = "LeastPrivilegeWriterPwd!2026";
        var payload = new AgentSecretsPayload(
            ApiBaseUrl: "http://127.0.0.1:8000",
            BearerToken: "bearer_tok_123",
            MysqlConnectionString: "Server=127.0.0.1;Uid=mock_pos_reader;Pwd=ReadPwd;",
            TenantId: 1,
            DataSourceId: 1,
            ConnectorInstanceId: 2,
            DeviceId: "D1",
            DeviceName: "Store",
            ScopeName: "CurrentUser",
            MysqlWriteConnectionString: $"Server=127.0.0.1;Port=3307;Database=mock_pos_db;Uid=mock_pos_writer;Pwd={writePwd};",
            CommandSigningPublicKeyEd25519: "pubKeyB64=="
        );

        store.Save(file, payload, DataProtectionScope.CurrentUser);
        var diskText = File.ReadAllText(file);
        Assert.DoesNotContain(writePwd, diskText, StringComparison.Ordinal);
        Assert.DoesNotContain("mock_pos_writer", diskText, StringComparison.Ordinal);

        var loaded = store.Load(file, DataProtectionScope.CurrentUser);
        Assert.NotNull(loaded);
        Assert.Contains(writePwd, loaded.MysqlWriteConnectionString ?? string.Empty, StringComparison.Ordinal);
        Assert.Equal("pubKeyB64==", loaded.CommandSigningPublicKeyEd25519);
    }

    private SqliteOutboxStore CreateStore()
    {
        var store = new SqliteOutboxStore(Path.Combine(_tempDir, $"cmd-{Guid.NewGuid():N}.sqlite"));
        store.EnsureInitialized();
        return store;
    }

    private static AgentSecretsPayload CreateSecrets(string pubKey) => new(
        ApiBaseUrl: "http://127.0.0.1:8000",
        BearerToken: "tok",
        MysqlConnectionString: "Server=mock_read;",
        TenantId: 1,
        DataSourceId: 1,
        ConnectorInstanceId: 1,
        DeviceId: "DEV-01",
        DeviceName: "Test",
        ScopeName: "CurrentUser",
        MysqlWriteConnectionString: "Server=mock_write;",
        CommandSigningPublicKeyEd25519: pubKey
    );

    private static (string PublicKeyB64, Ed25519PrivateKeyParameters PrivateKey) GenerateEd25519Pair()
    {
        var gen = new Ed25519KeyPairGenerator();
        gen.Init(new Ed25519KeyGenerationParameters(new SecureRandom()));
        var kp = gen.GenerateKeyPair();
        var pub = ((Ed25519PublicKeyParameters)kp.Public).GetEncoded();
        var priv = (Ed25519PrivateKeyParameters)kp.Private;
        return (Convert.ToBase64String(pub), priv);
    }

    private static PendingCommandEnvelope CreateSignedEnvelope(
        long commandId,
        string commandType,
        string targetExternalId,
        string payloadJson,
        string preconditionJson,
        string idempotencyKey,
        string expiresAtUtc,
        Ed25519PrivateKeyParameters privKey)
    {
        var unsigned = new PendingCommandEnvelope(
            CommandId: commandId,
            TenantId: 1,
            DataSourceId: 1,
            CommandType: commandType,
            TargetExternalId: targetExternalId,
            Payload: JsonDocument.Parse(payloadJson).RootElement.Clone(),
            Precondition: JsonDocument.Parse(preconditionJson).RootElement.Clone(),
            IdempotencyKey: idempotencyKey,
            ExpiresAt: expiresAtUtc,
            SignatureEd25519: string.Empty,
            SigningKeyId: "cmd-key-v1"
        );

        var canonical = Ed25519SignatureVerifier.CanonicalizeEnvelope(unsigned);
        var msgBytes = Encoding.UTF8.GetBytes(canonical);
        var signer = new Ed25519Signer();
        signer.Init(true, privKey);
        signer.BlockUpdate(msgBytes, 0, msgBytes.Length);
        var sig = Convert.ToBase64String(signer.GenerateSignature());

        return unsigned with { SignatureEd25519 = sig };
    }

    private sealed class RecordingTargetWriter(CommandWriteOutcome outcome) : ICommandTargetWriter
    {
        public int InvocationCount { get; private set; }

        public Task<CommandWriteOutcome> ExecuteWriteAsync(string writeConnectionString, PendingCommandEnvelope command, CancellationToken ct)
        {
            InvocationCount++;
            return Task.FromResult(outcome);
        }
    }

    private sealed class RecordingApiClient : IPlatformApiClient
    {
        public List<(long CommandId, string Status, string? ErrorCode)> Acks { get; } = [];

        public Task<EnrollResponse> EnrollAsync(string apiBaseUrl, string enrollmentCode, string deviceId, string deviceName, string agentVersion, CancellationToken ct)
            => throw new NotImplementedException();

        public Task<AgentConfigResponse?> FetchConfigAsync(string apiBaseUrl, string bearerToken, CancellationToken ct)
            => Task.FromResult<AgentConfigResponse?>(null);

        public Task<(bool Success, int StatusCode, SyncBatchResponse? Body, string? Error)> SendSyncBatchAsync(
            string apiBaseUrl, string bearerToken, string idempotencyKey, string runType, IReadOnlyList<OutboxEventRow> events, CancellationToken ct)
            => throw new NotImplementedException();

        public Task<bool> SendHeartbeatAsync(string apiBaseUrl, string bearerToken, string agentVersion, int outboxQueueDepth, string lastSyncStatus, CancellationToken ct)
            => Task.FromResult(true);

        public Task<List<PendingCommandEnvelope>> FetchPendingCommandsAsync(string apiBaseUrl, string bearerToken, CancellationToken ct)
            => Task.FromResult(new List<PendingCommandEnvelope>());

        public Task<bool> AcknowledgeCommandAsync(
            string apiBaseUrl, string bearerToken, long commandId, string status, Dictionary<string, object?>? result, string? errorCode, string? errorMessage, CancellationToken ct)
        {
            Acks.Add((commandId, status, errorCode));
            return Task.FromResult(true);
        }
    }
}
