using System.Globalization;
using Microsoft.Extensions.Logging;
using RemoteSyncAgent.Security;
using RemoteSyncAgent.Storage;
using RemoteSyncAgent.Transport;

namespace RemoteSyncAgent.Commands;

public sealed class CommandExecutionService
{
    private static readonly HashSet<string> AllowedCommandTypes = new(StringComparer.Ordinal)
    {
        "product.price.update",
        "order.status.update",
        "inventory.stock.adjust",
    };

    private readonly ILogger<CommandExecutionService> _logger;
    private readonly ICommandTargetWriter _targetWriter;
    private readonly IPlatformApiClient _apiClient;

    public CommandExecutionService(
        ILogger<CommandExecutionService> logger,
        ICommandTargetWriter targetWriter,
        IPlatformApiClient apiClient)
    {
        _logger = logger;
        _targetWriter = targetWriter;
        _apiClient = apiClient;
    }

    public async Task<int> PollAndExecuteAsync(
        AgentSecretsPayload secrets,
        SqliteOutboxStore store,
        string? effectivePublicKeyBase64,
        CancellationToken ct,
        DateTimeOffset? clockOverride = null)
    {
        var pending = await _apiClient.FetchPendingCommandsAsync(secrets.ApiBaseUrl, secrets.BearerToken, ct);
        if (pending.Count == 0)
        {
            return 0;
        }

        var processed = 0;
        foreach (var cmd in pending)
        {
            await ExecuteSingleAsync(secrets, store, effectivePublicKeyBase64, cmd, ct, clockOverride);
            processed++;
        }

        return processed;
    }

    public async Task<CommandWriteOutcome> ExecuteSingleAsync(
        AgentSecretsPayload secrets,
        SqliteOutboxStore store,
        string? effectivePublicKeyBase64,
        PendingCommandEnvelope cmd,
        CancellationToken ct,
        DateTimeOffset? clockOverride = null)
    {
        var now = clockOverride ?? DateTimeOffset.UtcNow;
        var pubKey = !string.IsNullOrWhiteSpace(secrets.CommandSigningPublicKeyEd25519)
            ? secrets.CommandSigningPublicKeyEd25519
            : (effectivePublicKeyBase64 ?? string.Empty);

        // 1. Verify Ed25519 signature before trusting ANY command field
        if (!Ed25519SignatureVerifier.Verify(cmd, pubKey))
        {
            _logger.LogWarning("Rejected remote command #{CmdId}: invalid Ed25519 signature.", cmd.CommandId);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: "INVALID_SIGNATURE",
                errorMessage: "Ed25519 signature verification failed on agent.",
                ct);
            return new CommandWriteOutcome(false, "INVALID_SIGNATURE", "Ed25519 signature verification failed on agent.", null);
        }

        // 2. Verify TTL expiry
        if (!DateTimeOffset.TryParse(cmd.ExpiresAt, CultureInfo.InvariantCulture, DateTimeStyles.AssumeUniversal | DateTimeStyles.AdjustToUniversal, out var expiresAt) ||
            expiresAt <= now)
        {
            _logger.LogWarning("Rejected remote command #{CmdId}: command TTL expired ({ExpiresAt}).", cmd.CommandId, cmd.ExpiresAt);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: "COMMAND_EXPIRED",
                errorMessage: "Command expired before agent execution.",
                ct);
            return new CommandWriteOutcome(false, "COMMAND_EXPIRED", "Command expired before agent execution.", null);
        }

        // 3. Verify strict replay protection via SQLite executed_commands
        if (store.HasExecutedCommand(cmd.CommandId, cmd.IdempotencyKey))
        {
            _logger.LogWarning("Rejected replay of remote command #{CmdId} (key={Key}).", cmd.CommandId, cmd.IdempotencyKey);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: "REPLAY_REJECTED",
                errorMessage: "Command replay rejected by SQLite local idempotency guard.",
                ct);
            return new CommandWriteOutcome(false, "REPLAY_REJECTED", "Command replay rejected by SQLite local idempotency guard.", null);
        }

        // 4. Strictly allow-listed command types only (ZERO arbitrary SQL)
        if (!AllowedCommandTypes.Contains(cmd.CommandType))
        {
            _logger.LogWarning("Rejected unsupported command type [{Type}] on command #{CmdId}.", cmd.CommandType, cmd.CommandId);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: "UNSUPPORTED_COMMAND",
                errorMessage: $"Command type [{cmd.CommandType}] is not allowed.",
                ct);
            return new CommandWriteOutcome(false, "UNSUPPORTED_COMMAND", $"Command type [{cmd.CommandType}] is not allowed.", null);
        }

        // 5. Require separate least-privilege write credentials (NEVER reuse read-only credentials)
        if (string.IsNullOrWhiteSpace(secrets.MysqlWriteConnectionString))
        {
            _logger.LogWarning("Rejected write command #{CmdId}: separate write credentials not configured.", cmd.CommandId);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: "WRITE_CREDENTIALS_NOT_CONFIGURED",
                errorMessage: "Agent write connection string (mock_pos_writer) is not configured in DPAPI store.",
                ct);
            return new CommandWriteOutcome(false, "WRITE_CREDENTIALS_NOT_CONFIGURED", "Write credentials not configured.", null);
        }

        // Mark executing on platform
        await _apiClient.AcknowledgeCommandAsync(
            secrets.ApiBaseUrl,
            secrets.BearerToken,
            cmd.CommandId,
            status: "executing",
            result: null,
            errorCode: null,
            errorMessage: null,
            ct);

        // 6. Execute parameterized write with optimistic concurrency precondition
        var outcome = await _targetWriter.ExecuteWriteAsync(secrets.MysqlWriteConnectionString, cmd, ct);

        if (outcome.Success)
        {
            store.RecordExecutedCommand(cmd.CommandId, cmd.IdempotencyKey, cmd.CommandType, "succeeded", now);
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "succeeded",
                result: outcome.Result,
                errorCode: null,
                errorMessage: null,
                ct);
            _logger.LogInformation("Remote command #{CmdId} ({Type}) executed and acknowledged.", cmd.CommandId, cmd.CommandType);
        }
        else
        {
            await _apiClient.AcknowledgeCommandAsync(
                secrets.ApiBaseUrl,
                secrets.BearerToken,
                cmd.CommandId,
                status: "failed",
                result: null,
                errorCode: outcome.ErrorCode ?? "EXECUTION_FAILED",
                errorMessage: SecretRedactor.Redact(outcome.ErrorMessage),
                ct);
            _logger.LogWarning("Remote command #{CmdId} failed ({Code}): {Err}", cmd.CommandId, outcome.ErrorCode, SecretRedactor.Redact(outcome.ErrorMessage));
        }

        return outcome;
    }
}
