<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\SystemHealthReport;
use App\Infrastructure\Messaging\ReverbProbe;
use App\Infrastructure\Persistence\MockPosReadOnlyProbe;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SystemHealthService
{
    public function __construct(
        private readonly MockPosReadOnlyProbe $mockPosProbe,
        private readonly ReverbProbe $reverbProbe,
    ) {}

    public function collect(): SystemHealthReport
    {
        $platformDb = $this->checkPlatformDatabase();
        $mockPosDb = $this->mockPosProbe->inspect();
        $reverb = $this->reverbProbe->inspect();

        $locale = app()->getLocale();
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';

        $overallOk = $platformDb['status'] === 'ok'
            && $mockPosDb['status'] === 'ok'
            && $reverb['status'] === 'ok';

        return new SystemHealthReport(
            overallStatus: $overallOk ? 'ok' : 'degraded',
            platformDb: $platformDb,
            mockPosDb: $mockPosDb,
            reverb: $reverb,
            runtime: [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'deployment_mode' => (string) config('app.deployment_mode', 'saas'),
                'locale' => $locale,
                'direction' => $direction,
            ],
            checkedAtIso: now()->utc()->toIso8601String(),
        );
    }

    /**
     * @return array{status: string, database: string, engine: string, latency_ms: float|null, error: string|null}
     */
    private function checkPlatformDatabase(): array
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver", $connectionName);
        $database = (string) config("database.connections.{$connectionName}.database", 'rbd_platform');
        $start = microtime(true);

        try {
            $query = $driver === 'sqlite' ? 'SELECT sqlite_version() AS version' : 'SELECT VERSION() AS version';
            $row = DB::selectOne($query);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'database' => $database,
                'engine' => (string) ($row->version ?? 'unknown'),
                'latency_ms' => $latency,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'database' => $database,
                'engine' => 'unavailable',
                'latency_ms' => null,
                'error' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Connection failed',
            ];
        }
    }
}
