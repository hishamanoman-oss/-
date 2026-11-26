<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

final class ReverbProbe
{
    /**
     * Check live TCP reachability of the configured Laravel Reverb WebSocket server.
     *
     * @return array{status: string, host: string, port: int, scheme: string, error: string|null}
     */
    public function inspect(): array
    {
        $host = (string) (config('broadcasting.connections.reverb.options.host') ?: env('REVERB_HOST', '127.0.0.1'));
        $port = (int) (config('broadcasting.connections.reverb.options.port') ?: env('REVERB_PORT', 8080));
        $scheme = (string) (config('broadcasting.connections.reverb.options.scheme') ?: env('REVERB_SCHEME', 'http'));

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.8);

        if (is_resource($socket)) {
            fclose($socket);

            return [
                'status' => 'ok',
                'host' => $host,
                'port' => $port,
                'scheme' => $scheme,
                'error' => null,
            ];
        }

        return [
            'status' => 'unreachable',
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'error' => $errstr !== '' ? $errstr : "Socket error #{$errno}",
        ];
    }
}
