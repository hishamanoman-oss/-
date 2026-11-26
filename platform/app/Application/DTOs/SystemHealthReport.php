<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class SystemHealthReport
{
    /**
     * @param  array{status: string, database: string, engine: string, latency_ms: float|null, error: string|null}  $platformDb
     * @param  array{status: string, database: string, counts: array<string, int>, error: string|null}  $mockPosDb
     * @param  array{status: string, host: string, port: int, scheme: string, error: string|null}  $reverb
     * @param  array{php: string, laravel: string, deployment_mode: string, locale: string, direction: string}  $runtime
     */
    public function __construct(
        public string $overallStatus,
        public array $platformDb,
        public array $mockPosDb,
        public array $reverb,
        public array $runtime,
        public string $checkedAtIso,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->overallStatus,
            'service' => 'remote-business-platform',
            'deployment_mode' => $this->runtime['deployment_mode'],
            'versions' => [
                'php' => $this->runtime['php'],
                'laravel' => $this->runtime['laravel'],
                'database' => $this->platformDb['engine'],
            ],
            'runtime' => $this->runtime,
            'checks' => [
                'rbd_platform' => $this->platformDb,
                'mock_pos_db' => $this->mockPosDb,
                'reverb' => $this->reverb,
            ],
            'timestamp' => $this->checkedAtIso,
        ];
    }
}
