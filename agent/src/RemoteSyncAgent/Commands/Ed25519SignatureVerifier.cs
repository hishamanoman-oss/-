using System.Text;
using System.Text.Encodings.Web;
using System.Text.Json;
using System.Text.Json.Serialization;
using Org.BouncyCastle.Crypto.Parameters;
using Org.BouncyCastle.Crypto.Signers;

namespace RemoteSyncAgent.Commands;

public sealed record PendingCommandEnvelope(
    [property: JsonPropertyName("command_id")] long CommandId,
    [property: JsonPropertyName("tenant_id")] long TenantId,
    [property: JsonPropertyName("data_source_id")] long DataSourceId,
    [property: JsonPropertyName("command_type")] string CommandType,
    [property: JsonPropertyName("target_external_id")] string TargetExternalId,
    [property: JsonPropertyName("payload")] JsonElement Payload,
    [property: JsonPropertyName("precondition")] JsonElement Precondition,
    [property: JsonPropertyName("idempotency_key")] string IdempotencyKey,
    [property: JsonPropertyName("expires_at")] string ExpiresAt,
    [property: JsonPropertyName("signature_ed25519")] string SignatureEd25519,
    [property: JsonPropertyName("signing_key_id")] string SigningKeyId
);

public static class Ed25519SignatureVerifier
{
    private static readonly JsonSerializerOptions CanonicalJsonOptions = new()
    {
        Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping,
        WriteIndented = false,
    };

    public static string CanonicalizeEnvelope(PendingCommandEnvelope envelope)
    {
        var sorted = new SortedDictionary<string, object?>(StringComparer.Ordinal)
        {
            ["command_id"] = envelope.CommandId,
            ["command_type"] = envelope.CommandType,
            ["data_source_id"] = envelope.DataSourceId,
            ["expires_at"] = envelope.ExpiresAt,
            ["idempotency_key"] = envelope.IdempotencyKey,
            ["payload"] = SortJsonElement(envelope.Payload),
            ["precondition"] = SortJsonElement(envelope.Precondition),
            ["target_external_id"] = envelope.TargetExternalId,
            ["tenant_id"] = envelope.TenantId,
        };

        return JsonSerializer.Serialize(sorted, CanonicalJsonOptions);
    }

    public static bool Verify(PendingCommandEnvelope envelope, string publicKeyBase64)
    {
        if (string.IsNullOrWhiteSpace(publicKeyBase64) || string.IsNullOrWhiteSpace(envelope.SignatureEd25519))
        {
            return false;
        }

        byte[] publicKeyBytes;
        byte[] signatureBytes;
        try
        {
            publicKeyBytes = Convert.FromBase64String(publicKeyBase64.Trim());
            signatureBytes = Convert.FromBase64String(envelope.SignatureEd25519.Trim());
        }
        catch (FormatException)
        {
            return false;
        }

        if (publicKeyBytes.Length != 32 || signatureBytes.Length != 64)
        {
            return false;
        }

        var canonical = CanonicalizeEnvelope(envelope);
        var messageBytes = Encoding.UTF8.GetBytes(canonical);

        var verifier = new Ed25519Signer();
        verifier.Init(false, new Ed25519PublicKeyParameters(publicKeyBytes, 0));
        verifier.BlockUpdate(messageBytes, 0, messageBytes.Length);
        return verifier.VerifySignature(signatureBytes);
    }

    private static object? SortJsonElement(JsonElement el)
    {
        return el.ValueKind switch
        {
            JsonValueKind.Object => SortJsonObject(el),
            JsonValueKind.Array => el.EnumerateArray().Select(SortJsonElement).ToList(),
            JsonValueKind.String => el.GetString(),
            JsonValueKind.Number => el.TryGetInt64(out var l) ? l : el.GetDecimal(),
            JsonValueKind.True => true,
            JsonValueKind.False => false,
            _ => null,
        };
    }

    private static SortedDictionary<string, object?> SortJsonObject(JsonElement obj)
    {
        var dict = new SortedDictionary<string, object?>(StringComparer.Ordinal);
        foreach (var prop in obj.EnumerateObject())
        {
            dict[prop.Name] = SortJsonElement(prop.Value);
        }
        return dict;
    }
}
