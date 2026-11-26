using System.Security.Cryptography;

namespace RemoteSyncAgent.Configuration;

public sealed class AgentOptions
{
    public const string SectionName = "Agent";

    public string AgentVersion { get; set; } = "10.0.1";
    public int PollIntervalSeconds { get; set; } = 30;
    public int HeartbeatIntervalSeconds { get; set; } = 20;
    public int MaxBatchSize { get; set; } = 250;
    public int InitialBackoffSeconds { get; set; } = 2;
    public int MaxBackoffSeconds { get; set; } = 60;
    public string DataDirectory { get; set; } = string.Empty;
    public string ProtectionScope { get; set; } = "CurrentUser";

    public string ResolveDataDirectory()
    {
        if (!string.IsNullOrWhiteSpace(DataDirectory))
        {
            Directory.CreateDirectory(DataDirectory);
            return DataDirectory;
        }

        var path = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "RemoteBusinessPlatform",
            "SyncAgent"
        );
        Directory.CreateDirectory(path);
        return path;
    }

    public DataProtectionScope ResolveProtectionScope()
    {
        return string.Equals(ProtectionScope, "LocalMachine", StringComparison.OrdinalIgnoreCase)
            ? DataProtectionScope.LocalMachine
            : DataProtectionScope.CurrentUser;
    }
}
