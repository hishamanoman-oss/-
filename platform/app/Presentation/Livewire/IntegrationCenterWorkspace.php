<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Application\Services\AuditLogger;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\FieldMapping;
use App\Domain\Synchronization\Models\SyncError;
use App\Domain\Synchronization\Models\SyncRun;
use App\Domain\Synchronization\Services\FreshnessEvaluator;
use App\Domain\Synchronization\Services\ReconciliationService;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

final class IntegrationCenterWorkspace extends Component
{
    public string $activeTab = 'sources';

    public ?int $selectedSourceId = null;

    /** @var array{ok: bool, message: string}|null */
    public ?array $connectionTestResult = null;

    /** @var array<string, array<string, string>> */
    public array $discoveredSchema = [];

    /** @var array<string, mixed>|null */
    public ?array $reconciliationResult = null;

    public ?string $generatedEnrollmentCode = null;

    public string $newDeviceName = 'Windows Store Sync Agent';

    // New Data Source Modal
    public bool $showCreateSourceModal = false;

    public string $sourceName = '';

    public string $connectorType = 'local_mysql_agent';

    public string $captureMode = 'incremental_polling';

    public int $syncIntervalSeconds = 30;

    // Add/Update Field Mapping form
    public string $mapEntity = 'product';

    public string $mapSourceTable = 'tbl_items';

    public string $mapSourceColumn = '';

    public string $mapCanonicalField = '';

    public string $mapTransformRule = 'trim';

    public bool $mapIsRequired = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', DataSource::class);

        $first = DataSource::query()->orderBy('id')->first();
        $this->selectedSourceId = $first?->id;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['sources', 'enrollment', 'mappings', 'runs'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function selectSource(int $sourceId): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('view', $source);

        $this->selectedSourceId = $source->id;
        $this->connectionTestResult = null;
        $this->discoveredSchema = [];
        $this->reconciliationResult = null;
        $this->generatedEnrollmentCode = null;
    }

    public function openCreateSource(): void
    {
        Gate::authorize('manage', DataSource::class);

        $this->resetValidation();
        $this->sourceName = '';
        $this->connectorType = 'local_mysql_agent';
        $this->captureMode = 'incremental_polling';
        $this->syncIntervalSeconds = 30;
        $this->showCreateSourceModal = true;
    }

    public function saveSource(AuditLogger $audit): void
    {
        Gate::authorize('manage', DataSource::class);

        $validated = $this->validate([
            'sourceName' => ['required', 'string', 'max:150'],
            'connectorType' => ['required', 'in:local_mysql_agent,local_mysql_direct_test,webhook_generic,csv_import'],
            'captureMode' => ['required', 'in:incremental_polling,cdc_binlog,webhooks,manual_csv'],
            'syncIntervalSeconds' => ['required', 'integer', 'min:10', 'max:3600'],
        ]);

        $source = DataSource::query()->create([
            'name' => trim($validated['sourceName']),
            'connector_type' => $validated['connectorType'],
            'status' => 'active',
            'freshness_status' => 'disconnected',
            'capture_mode' => $validated['captureMode'],
            'sync_interval_seconds' => $validated['syncIntervalSeconds'],
            'capabilities_json' => [
                'supports_read' => true,
                'supports_write' => false,
                'supports_polling' => true,
                'supports_cdc' => $validated['connectorType'] !== 'webhook_generic',
                'supports_webhooks' => $validated['connectorType'] === 'webhook_generic',
                'allowed_write_commands' => [],
            ],
            'config_encrypted' => [
                'connection_profile' => 'mock_pos',
                'webhook_secret' => bin2hex(random_bytes(16)),
            ],
        ]);

        $audit->log('integration.source_created', 'data_source', $source->id, ['name' => $source->name]);

        $this->selectedSourceId = $source->id;
        $this->showCreateSourceModal = false;
        $this->dispatch('toast', message: __('ui.created_successfully'), type: 'success');
    }

    public function testConnection(int $sourceId): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $connector = new LocalMysqlReadOnlyConnector($source);
        $res = $connector->testConnection();
        $connector->disconnect();

        if ($res['ok']) {
            $this->connectionTestResult = [
                'ok' => true,
                'message' => __('ui.connection_ok', [
                    'engine' => $res['engine'] ?? 'MySQL',
                    'database' => $res['database'] ?? '',
                ]),
            ];
        } else {
            $this->connectionTestResult = [
                'ok' => false,
                'message' => $res['error'] ?? 'Connection failed',
            ];
        }
    }

    public function toggleSourceStatus(int $sourceId, AuditLogger $audit): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $newStatus = $source->status === 'paused' ? 'active' : 'paused';
        $source->forceFill(['status' => $newStatus])->save();

        $audit->log('integration.source_status_changed', 'data_source', $source->id, ['status' => $newStatus]);
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function runSyncNow(int $sourceId, SyncIngestionService $ingestionService): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $this->selectedSourceId = $source->id;
        $connector = new LocalMysqlReadOnlyConnector($source);

        try {
            $run = $ingestionService->syncFromConnector($source, $connector, 'manual');
            $this->dispatch(
                'toast',
                message: __('ui.sync_completed_summary', [
                    'upserted' => $run->records_upserted,
                    'skipped' => $run->records_skipped_duplicate,
                ]),
                type: $run->status === 'failed' ? 'error' : 'success'
            );
        } catch (Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } finally {
            $connector->disconnect();
        }
    }

    public function discoverSchema(int $sourceId): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $this->selectedSourceId = $source->id;
        $connector = new LocalMysqlReadOnlyConnector($source);

        try {
            $this->discoveredSchema = $connector->discoverSchema();
            $this->activeTab = 'mappings';
        } catch (Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } finally {
            $connector->disconnect();
        }
    }

    public function saveMapping(): void
    {
        $source = $this->selectedSource();
        if ($source === null) {
            return;
        }
        Gate::authorize('manage', $source);

        $validated = $this->validate([
            'mapEntity' => ['required', 'in:branch,warehouse,customer,product,inventory_balance,order,order_item'],
            'mapSourceTable' => ['required', 'string', 'max:120'],
            'mapSourceColumn' => ['required', 'string', 'max:120'],
            'mapCanonicalField' => ['required', 'string', 'max:120'],
            'mapTransformRule' => ['nullable', 'in:,trim,decimal,iso_date,uppercase,map_status'],
            'mapIsRequired' => ['boolean'],
        ]);

        FieldMapping::query()->updateOrCreate(
            [
                'data_source_id' => $source->id,
                'canonical_entity' => $validated['mapEntity'],
                'canonical_field' => trim($validated['mapCanonicalField']),
            ],
            [
                'source_table' => trim($validated['mapSourceTable']),
                'source_column' => trim($validated['mapSourceColumn']),
                'transform_rule' => $validated['mapTransformRule'] !== '' ? $validated['mapTransformRule'] : null,
                'is_required' => (bool) $validated['mapIsRequired'],
            ]
        );

        $this->mapSourceColumn = '';
        $this->mapCanonicalField = '';
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function generateEnrollmentCode(int $sourceId, AuditLogger $audit): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $this->validate([
            'newDeviceName' => ['required', 'string', 'max:150'],
        ]);

        $plainCode = 'RBD-'.strtoupper(Str::random(6)).'-'.strtoupper(Str::random(6));

        $instance = ConnectorInstance::query()->create([
            'data_source_id' => $source->id,
            'device_id' => 'pending-'.Str::uuid()->toString(),
            'device_name' => trim($this->newDeviceName),
            'enrollment_code_hash' => hash('sha256', $plainCode),
            'enrollment_expires_at' => now()->addMinutes(30),
            'status' => 'pending_enrollment',
        ]);

        $this->generatedEnrollmentCode = $plainCode;
        $this->selectedSourceId = $source->id;
        $this->activeTab = 'enrollment';

        $audit->log('connector.enrollment_generated', 'connector_instance', $instance->id, [
            'data_source_id' => $source->id,
            'device_name' => $instance->device_name,
        ]);

        $this->dispatch('toast', message: __('ui.enrollment_generated'), type: 'success');
    }

    public function revokeInstance(int $instanceId, AuditLogger $audit, FreshnessEvaluator $evaluator): void
    {
        $instance = ConnectorInstance::query()->with('dataSource')->findOrFail($instanceId);
        Gate::authorize('manage', $instance->dataSource);

        $instance->forceFill([
            'status' => 'revoked',
            'token_hash' => null,
            'enrollment_code_hash' => null,
        ])->save();

        $audit->log('connector.revoked', 'connector_instance', $instance->id, [
            'data_source_id' => $instance->data_source_id,
            'device_id' => $instance->device_id,
        ]);

        $evaluator->refreshAndSave($instance->dataSource);
        $this->dispatch('toast', message: __('ui.instance_revoked'), type: 'success');
    }

    public function runReconciliation(int $sourceId, ReconciliationService $reconciliationService): void
    {
        $source = DataSource::query()->findOrFail($sourceId);
        Gate::authorize('manage', $source);

        $this->selectedSourceId = $source->id;
        $this->reconciliationResult = $reconciliationService->reconcile($source);
        $this->activeTab = 'runs';
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', DataSource::class);

        $sources = DataSource::query()->withCount(['instances', 'fieldMappings'])->orderBy('id')->get();
        $selectedSource = $this->selectedSource();
        if ($selectedSource === null && $sources->isNotEmpty()) {
            $selectedSource = $sources->first();
            $this->selectedSourceId = $selectedSource?->id;
        }

        $instances = $selectedSource
            ? ConnectorInstance::query()->where('data_source_id', $selectedSource->id)->latest('id')->get()
            : collect();

        $mappings = $selectedSource
            ? FieldMapping::query()->where('data_source_id', $selectedSource->id)->orderBy('canonical_entity')->orderBy('id')->get()
            : collect();

        $syncRuns = $selectedSource
            ? SyncRun::query()->where('data_source_id', $selectedSource->id)->latest('id')->limit(15)->get()
            : collect();

        $syncErrors = $selectedSource
            ? SyncError::query()->where('data_source_id', $selectedSource->id)->latest('id')->limit(15)->get()
            : collect();

        return view('livewire.integration-center-workspace', [
            'sources' => $sources,
            'selectedSource' => $selectedSource,
            'instances' => $instances,
            'mappings' => $mappings,
            'syncRuns' => $syncRuns,
            'syncErrors' => $syncErrors,
            'canManage' => $tenantContext->hasPermission('integrations.manage'),
        ]);
    }

    private function selectedSource(): ?DataSource
    {
        if ($this->selectedSourceId === null) {
            return null;
        }

        return DataSource::query()->find($this->selectedSourceId);
    }
}
