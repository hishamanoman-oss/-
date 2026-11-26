<?php

declare(strict_types=1);

namespace App\Domain\Tenants\Services;

use App\Domain\Tenants\Models\License;
use Carbon\CarbonImmutable;
use Throwable;

final class Ed25519LicenseService
{
    /**
     * @return array{public_key: string, secret_key: string}
     */
    public function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signPayload(array $payload, string $secretKeyBase64): string
    {
        $secretKey = base64_decode($secretKeyBase64, true);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('Invalid Ed25519 secret key.');
        }

        $canonical = $this->canonicalJson($payload);
        $signature = sodium_crypto_sign_detached($canonical, $secretKey);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, reason: string|null}
     */
    public function verify(array $payload, string $signatureBase64, ?string $publicKeyBase64 = null): array
    {
        $publicKeyB64 = $publicKeyBase64 ?: (string) config('platform.license_public_key', '');
        if ($publicKeyB64 === '') {
            return ['valid' => false, 'reason' => 'Missing license verification public key.'];
        }

        $publicKey = base64_decode($publicKeyB64, true);
        $signature = base64_decode($signatureBase64, true);

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return ['valid' => false, 'reason' => 'Invalid Ed25519 public key format.'];
        }

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return ['valid' => false, 'reason' => 'Invalid Ed25519 signature format.'];
        }

        try {
            $canonical = $this->canonicalJson($payload);
            $verified = sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
        } catch (Throwable) {
            return ['valid' => false, 'reason' => 'Cryptographic signature verification failed.'];
        }

        if (! $verified) {
            return ['valid' => false, 'reason' => 'License signature mismatch (payload or signature tampered).'];
        }

        if (empty($payload['license_key_id']) || empty($payload['edition']) || empty($payload['expires_at'])) {
            return ['valid' => false, 'reason' => 'Incomplete license payload metadata.'];
        }

        try {
            $expiresAt = CarbonImmutable::parse((string) $payload['expires_at']);
        } catch (Throwable) {
            return ['valid' => false, 'reason' => 'Invalid license expiration timestamp.'];
        }

        if ($expiresAt->isPast()) {
            return ['valid' => false, 'reason' => 'License has expired.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @return array{valid: bool, reason: string|null}
     */
    public function verifyLicenseRecord(License $license, ?string $publicKeyBase64 = null): array
    {
        if ($license->status === 'revoked') {
            return ['valid' => false, 'reason' => 'License is revoked.'];
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($license->payload_json) ? $license->payload_json : [];
        $result = $this->verify($payload, $license->signature, $publicKeyBase64);

        if ($result['valid']) {
            $license->forceFill([
                'status' => 'active',
                'verified_at' => now(),
            ])->save();
        } elseif ($result['reason'] === 'License has expired.') {
            $license->forceFill(['status' => 'expired'])->save();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function canonicalJson(array $data): string
    {
        $sorted = $this->recursiveKeySort($data);

        return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function recursiveKeySort(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveKeySort($value);
            }
        }

        return $data;
    }
}
