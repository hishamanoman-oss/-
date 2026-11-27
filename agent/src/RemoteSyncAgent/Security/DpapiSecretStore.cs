using System.Runtime.Versioning;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace RemoteSyncAgent.Security;

public sealed record AgentSecretsPayload(
    string ApiBaseUrl,
    string BearerToken,
    string MysqlConnectionString,
    long TenantId,
    long DataSourceId,
    long ConnectorInstanceId,
    string DeviceId,
    string DeviceName,
    string ScopeName,
    string? MysqlWriteConnectionString = null,
    string? CommandSigningPublicKeyEd25519 = null
);

public interface ISecretStore
{
    void Save(string filePath, AgentSecretsPayload payload, DataProtectionScope scope);
    AgentSecretsPayload? Load(string filePath, DataProtectionScope scope);
}

[SupportedOSPlatform("windows")]
public sealed class DpapiSecretStore : ISecretStore
{
    // Fixed application-specific entropy bound to the Remote Business Platform Sync Agent
    private static readonly byte[] Entropy = Encoding.UTF8.GetBytes("RBD-SyncAgent-DPAPI-v1-Entropy");

    public void Save(string filePath, AgentSecretsPayload payload, DataProtectionScope scope)
    {
        ArgumentNullException.ThrowIfNull(payload);
        var dir = Path.GetDirectoryName(filePath);
        if (!string.IsNullOrWhiteSpace(dir))
        {
            Directory.CreateDirectory(dir);
        }

        var json = JsonSerializer.Serialize(payload);
        var plainBytes = Encoding.UTF8.GetBytes(json);
        try
        {
            var cipherBytes = ProtectedData.Protect(plainBytes, Entropy, scope);
            var encoded = Convert.ToBase64String(cipherBytes);
            File.WriteAllText(filePath, encoded, Encoding.UTF8);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(plainBytes);
        }
    }

    public AgentSecretsPayload? Load(string filePath, DataProtectionScope scope)
    {
        if (!File.Exists(filePath))
        {
            return null;
        }

        var encoded = File.ReadAllText(filePath, Encoding.UTF8).Trim();
        if (string.IsNullOrWhiteSpace(encoded))
        {
            return null;
        }

        var cipherBytes = Convert.FromBase64String(encoded);
        var plainBytes = ProtectedData.Unprotect(cipherBytes, Entropy, scope);
        try
        {
            var json = Encoding.UTF8.GetString(plainBytes);
            return JsonSerializer.Deserialize<AgentSecretsPayload>(json);
        }
        finally
        {
            CryptographicOperations.ZeroMemory(plainBytes);
        }
    }
}
