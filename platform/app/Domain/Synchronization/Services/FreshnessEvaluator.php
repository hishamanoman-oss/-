<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Services;

use App\Domain\Integrations\Models\DataSource;
use App\Infrastructure\Messaging\Events\DataFreshnessUpdated;
use Carbon\CarbonInterface;

final class FreshnessEvaluator
{
    public function evaluate(DataSource $source, ?CarbonInterface $now = null): string
    {
        $clock = $now ?? now();

        if ($source->status === 'error') {
            return 'error';
        }

        if ($source->syncRuns()->where('status', 'running')->exists()) {
            return 'syncing';
        }

        $heartbeat = $source->last_heartbeat_at ?? $source->last_successful_sync_at;
        if ($heartbeat === null) {
            return 'disconnected';
        }

        $interval = max(10, (int) $source->sync_interval_seconds);
        $disconnectThreshold = max(90, $interval * 3);
        $delayThreshold = max(60, $interval * 2);

        if ($heartbeat->diffInSeconds($clock, false) > $disconnectThreshold) {
            return 'disconnected';
        }

        if (
            $source->last_successful_sync_at === null
            || $source->last_successful_sync_at->diffInSeconds($clock, false) > $delayThreshold
        ) {
            return 'delayed';
        }

        return 'live';
    }

    public function refreshAndSave(DataSource $source, ?CarbonInterface $now = null): DataSource
    {
        $newStatus = $this->evaluate($source, $now);

        if ($source->freshness_status !== $newStatus) {
            $source->forceFill(['freshness_status' => $newStatus])->save();

            event(new DataFreshnessUpdated(
                tenantId: (int) $source->tenant_id,
                dataSourceId: (int) $source->id,
                freshnessStatus: $newStatus,
                lastHeartbeatAtIso: $source->last_heartbeat_at?->utc()->toIso8601String(),
                lastSuccessfulSyncAtIso: $source->last_successful_sync_at?->utc()->toIso8601String(),
            ));
        }

        return $source;
    }
}
