using System.Text.RegularExpressions;

namespace RemoteSyncAgent.Security;

public static partial class SecretRedactor
{
    [GeneratedRegex(@"(?i)(Bearer\s+)[A-Za-z0-9\-._~+/]+=*")]
    private static partial Regex BearerRegex();

    [GeneratedRegex(@"(?i)(Pwd|Password|Token|Secret|enrollment_code)\s*=\s*[^;,\s""'}]+")]
    private static partial Regex KeyValueSecretRegex();

    public static string Redact(string? input)
    {
        if (string.IsNullOrEmpty(input))
        {
            return string.Empty;
        }

        var step1 = BearerRegex().Replace(input, "$1[REDACTED]");
        return KeyValueSecretRegex().Replace(step1, "$1=[REDACTED]");
    }
}
