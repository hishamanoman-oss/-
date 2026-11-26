using System.Security.Cryptography;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using RemoteSyncAgent.Capture;
using RemoteSyncAgent.Configuration;
using RemoteSyncAgent.Security;
using RemoteSyncAgent.Storage;
using RemoteSyncAgent.Transport;
using RemoteSyncAgent.Workers;

if (OperatingSystem.IsWindows() is false)
{
    Console.Error.WriteLine("RemoteSyncAgent requires Windows (DPAPI + Windows Service support).");
    return 1;
}

if (args.Length > 0 && string.Equals(args[0], "enroll", StringComparison.OrdinalIgnoreCase))
{
    return await RunEnrollCommandAsync(args[1..]);
}

var cliOverrides = ParseRunCliArgs(args);

var builder = Host.CreateApplicationBuilder(args);
if (cliOverrides.Count > 0)
{
    builder.Configuration.AddInMemoryCollection(cliOverrides);
}

builder.Services.AddWindowsService(options =>
{
    options.ServiceName = "RemoteBusinessSyncAgent";
});

builder.Services.Configure<AgentOptions>(builder.Configuration.GetSection(AgentOptions.SectionName));
builder.Services.AddSingleton<ISecretStore, DpapiSecretStore>();
builder.Services.AddSingleton<IChangeCaptureProvider, IncrementalPollingCaptureProvider>();
builder.Services.AddHttpClient<IPlatformApiClient, PlatformApiClient>(client =>
{
    client.Timeout = TimeSpan.FromSeconds(15);
});
builder.Services.AddHostedService<SyncWorkerService>();

var host = builder.Build();
await host.RunAsync();
return 0;

static async Task<int> RunEnrollCommandAsync(string[] args)
{
    var parsed = ParseKeyValueFlags(args);

    var apiUrl = RequireFlag(parsed, "--api-url");
    var code = RequireFlag(parsed, "--code");
    var deviceId = RequireFlag(parsed, "--device-id");
    var deviceName = RequireFlag(parsed, "--device-name");
    var mysqlConn = RequireFlag(parsed, "--mysql-conn");
    var scopeArg = parsed.TryGetValue("--dpapi-scope", out var s) ? s : "CurrentUser";
    var dataDir = parsed.TryGetValue("--data-dir", out var d) ? d : string.Empty;

    var options = new AgentOptions
    {
        DataDirectory = dataDir,
        ProtectionScope = scopeArg,
    };

    var resolvedDir = options.ResolveDataDirectory();
    var secretsPath = Path.Combine(resolvedDir, "agent.secrets.dpapi");
    var sqlitePath = Path.Combine(resolvedDir, "agent-outbox.sqlite");

    using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
    var client = new PlatformApiClient(http);

    var res = await client.EnrollAsync(apiUrl, code, deviceId, deviceName, options.AgentVersion, CancellationToken.None);

    var store = new DpapiSecretStore();
    var scope = options.ResolveProtectionScope();
    store.Save(
        secretsPath,
        new AgentSecretsPayload(
            ApiBaseUrl: apiUrl.TrimEnd('/'),
            BearerToken: res.BearerToken,
            MysqlConnectionString: mysqlConn,
            TenantId: res.TenantId,
            DataSourceId: res.DataSourceId,
            ConnectorInstanceId: res.ConnectorInstanceId,
            DeviceId: deviceId,
            DeviceName: deviceName,
            ScopeName: scope.ToString()
        ),
        scope
    );

    var outbox = new SqliteOutboxStore(sqlitePath);
    outbox.EnsureInitialized();
    outbox.SetState("enrolled_at", DateTimeOffset.UtcNow.ToString("O"));

    Console.WriteLine($"Enrolled successfully! tenant_id={res.TenantId}, data_source_id={res.DataSourceId}, instance_id={res.ConnectorInstanceId}");
    Console.WriteLine($"Secrets encrypted via Windows DPAPI ({scope}) at: {secretsPath}");
    Console.WriteLine($"SQLite Outbox initialized at: {sqlitePath}");
    return 0;
}

static Dictionary<string, string?> ParseRunCliArgs(string[] args)
{
    var parsed = ParseKeyValueFlags(args);
    var dict = new Dictionary<string, string?>();

    if (parsed.TryGetValue("--data-dir", out var dataDir) && !string.IsNullOrWhiteSpace(dataDir))
    {
        dict["Agent:DataDirectory"] = dataDir;
    }

    if (parsed.TryGetValue("--poll-seconds", out var pollSec) && int.TryParse(pollSec, out var sec) && sec >= 1)
    {
        dict["Agent:PollIntervalSeconds"] = sec.ToString();
    }

    if (parsed.TryGetValue("--dpapi-scope", out var scope) && !string.IsNullOrWhiteSpace(scope))
    {
        dict["Agent:ProtectionScope"] = scope;
    }

    return dict;
}

static Dictionary<string, string> ParseKeyValueFlags(string[] args)
{
    var dict = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
    for (var i = 0; i < args.Length; i++)
    {
        if (!args[i].StartsWith("--", StringComparison.Ordinal))
        {
            continue;
        }

        if (i + 1 < args.Length && !args[i + 1].StartsWith("--", StringComparison.Ordinal))
        {
            dict[args[i]] = args[i + 1];
            i++;
        }
    }
    return dict;
}

static string RequireFlag(Dictionary<string, string> parsed, string flag)
{
    if (!parsed.TryGetValue(flag, out var value) || string.IsNullOrWhiteSpace(value))
    {
        throw new ArgumentException($"Missing required CLI argument: {flag}");
    }

    return value;
}
