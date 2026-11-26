<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DataFreshnessUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $dataSourceId,
        public readonly string $freshnessStatus,
        public readonly ?string $lastHeartbeatAtIso,
        public readonly ?string $lastSuccessfulSyncAtIso,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.freshness.updated';
    }
}
