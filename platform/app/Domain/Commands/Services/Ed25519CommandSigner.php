<?php

declare(strict_types=1);

namespace App\Domain\Commands\Services;

use InvalidArgumentException;
use Throwable;

final class Ed25519CommandSigner
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

    public function signingKeyId(): string
    {
        return (string) config('platform.command_signing_key_id', 'cmd-key-v1');
    }

    public function publicKeyBase64(): string
    {
        return (string) config('platform.command_signing_public_key', '');
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function signEnvelope(array $envelope, ?string $secretKeyBase64 = null): string
    {
        $secretB64 = $secretKeyBase64 ?: (string) config('platform.command_signing_private_key', '');
        $secretKey = base64_decode($secretB64, true);

        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Invalid or missing Ed25519 command signing private key.');
        }

        $canonical = $this->canonicalJson($envelope);
        $signature = sodium_crypto_sign_detached($canonical, $secretKey);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function verifyEnvelope(array $envelope, string $signatureBase64, ?string $publicKeyBase64 = null): bool
    {
        $pubB64 = $publicKeyBase64 ?: $this->publicKeyBase64();
        $publicKey = base64_decode($pubB64, true);
        $signature = base64_decode($signatureBase64, true);

        if (
            $publicKey === false
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || $signature === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
        ) {
            return false;
        }

        try {
            $canonical = $this->canonicalJson($envelope);

            return sodium_crypto_sign_verify_detached($signature, $canonical, $publicKey);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Canonical deterministic sorted-key JSON encoding matching C# Ed25519SignatureVerifier.
     *
     * @param  array<string, mixed>  $envelope
     */
    public function canonicalJson(array $envelope): string
    {
        $sorted = $this->sortRecursive($envelope);

        return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $k => $v) {
            $value[$k] = $this->sortRecursive($v);
        }

        return $value;
    }
}
