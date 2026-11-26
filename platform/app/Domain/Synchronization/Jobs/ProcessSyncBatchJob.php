<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Jobs;

use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessSyncBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<array{event_id: string, entity_type: string, external_id: string, payload: array<string, mixed>}>  $events
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $dataSourceId,
        public readonly array $events,
        public readonly ?int $connectorInstanceId = null,
        public readonly string $runType = 'incremental',
    ) {}

    public function handle(TenantContext $tenantContext, SyncIngestionService $ingestionService): void
    {
        $tenantContext->setTenantId($this->tenantId);

        try {
            $source = DataSource::query()->findOrFail($this->dataSourceId);
            $instance = $this->connectorInstanceId !== null
                ? ConnectorInstance::query()->find($this->connectorInstanceId)
                : null;

            $ingestionService->ingestBatch(
                source: $source,
                instance: $instance,
                events: $this->events,
                runType: $this->runType,
            );
        } finally {
            $tenantContext->clear();
        }
    }
}
