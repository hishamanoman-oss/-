<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Application\Services\AuditLogger;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Commands\Services\Ed25519CommandSigner;
use App\Domain\Commands\Services\RemoteCommandService;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\FieldMapping;
use App\Domain\Synchronization\Jobs\ProcessSyncBatchJob;
use App\Domain\Synchronization\Models\IdempotencyKey;
use App\Domain\Synchronization\Models\SyncCursor;
use App\Domain\Synchronization\Services\FreshnessEvaluator;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentController
{
    public function enroll(
        Request $request,
        TenantContext $tenantContext,
        AuditLogger $audit,
        FreshnessEvaluator $freshnessEvaluator,
    ): JsonResponse {
        $validated = $request->validate([
            'enrollment_code' => ['required', 'string', 'min:8'],
            'device_id' => ['required', 'string', 'max:120'],
            'device_name' => ['required', 'string', 'max:150'],
            'agent_version' => ['nullable', 'string', 'max:40'],
            'public_key_ed25519' => ['nullable', 'string'],
        ]);

        $codeHash = hash('sha256', trim($validated['enrollment_code']));

        /** @var ConnectorInstance|null $instance */
        $instance = $tenantContext->runWithoutTenantScope(fn () => ConnectorInstance::query()
            ->with(['tenant', 'dataSource'])
            ->where('enrollment_code_hash', $codeHash)
            ->where('status', 'pending_enrollment')
            ->first());

        if (
            $instance === null
            || $instance->enrollment_expires_at === null
            || $instance->enrollment_expires_at->isPast()
            || $instance->tenant === null
            || $instance->tenant->status !== 'active'
        ) {
            return response()->json(['message' => 'Invalid, used, or expired enrollment code.'], 401);
        }

        $tenantContext->bind($instance->tenant, null);

        try {
            $rawBearerToken = bin2hex(random_bytes(32));

            $instance->forceFill([
                'device_id' => trim($validated['device_id']),
                'device_name' => trim($validated['device_name']),
                'agent_version' => $validated['agent_version'] ?? null,
                'public_key_ed25519' => $validated['public_key_ed25519'] ?? null,
                'enrollment_code_hash' => null,
                'enrollment_expires_at' => null,
                'token_hash' => hash('sha256', $rawBearerToken),
                'status' => 'active',
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
            ])->save();

            $source = $instance->dataSource;
            $source->forceFill([
                'status' => 'active',
                'last_heartbeat_at' => now(),
            ])->save();
            $freshnessEvaluator->refreshAndSave($source);

            $audit->log('connector.enrolled', 'connector_instance', $instance->id, [
                'data_source_id' => $source->id,
                'device_id' => $instance->device_id,
                'device_name' => $instance->device_name,
            ], tenantId: (int) $instance->tenant_id, actorUserId: null);

            return response()->json([
                'tenant_id' => $instance->tenant_id,
                'data_source_id' => $instance->data_source_id,
                'connector_instance_id' => $instance->id,
                'bearer_token' => $rawBearerToken,
            ], 200);
        } finally {
            $tenantContext->clear();
        }
    }

    public function heartbeat(Request $request, FreshnessEvaluator $freshnessEvaluator): JsonResponse
    {
        /** @var ConnectorInstance $instance */
        $instance = $request->attributes->get('connector_instance');

        $validated = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:40'],
        ]);

        $now = now();
        $instance->forceFill([
            'last_seen_at' => $now,
            'last_ip' => $request->ip(),
            'agent_version' => $validated['agent_version'] ?? $instance->agent_version,
        ])->save();

        $source = $instance->dataSource;
        $source->forceFill([
            'status' => $source->status === 'paused' ? 'paused' : 'active',
            'last_heartbeat_at' => $now,
        ])->save();

        $freshnessEvaluator->refreshAndSave($source, $now);

        return response()->json([
            'status' => 'ok',
            'data_source_id' => $source->id,
            'freshness_status' => $source->freshness_status,
            'server_time' => $now->utc()->toIso8601String(),
        ]);
    }

    public function config(Request $request, Ed25519CommandSigner $signer): JsonResponse
    {
        /** @var ConnectorInstance $instance */
        $instance = $request->attributes->get('connector_instance');
        $source = $instance->dataSource;

        $mappings = FieldMapping::query()
            ->where('data_source_id', $source->id)
            ->orderBy('canonical_entity')
            ->orderBy('id')
            ->get([
                'canonical_entity',
                'source_table',
                'source_column',
                'canonical_field',
                'transform_rule',
                'default_value',
                'is_required',
            ]);

        $cursors = SyncCursor::query()
            ->where('data_source_id', $source->id)
            ->get(['source_table', 'cursor_column', 'last_cursor_value', 'updated_at']);

        $caps = is_array($source->capabilities_json) ? $source->capabilities_json : [];
        $allowedWriteCommands = (! empty($caps['supports_write']) && is_array($caps['allowed_write_commands'] ?? null))
            ? array_values($caps['allowed_write_commands'])
            : [];

        return response()->json([
            'data_source' => [
                'id' => $source->id,
                'name' => $source->name,
                'connector_type' => $source->connector_type,
                'capture_mode' => $source->capture_mode,
                'sync_interval_seconds' => $source->sync_interval_seconds,
                'freshness_status' => $source->freshness_status,
            ],
            'field_mappings' => $mappings,
            'sync_cursors' => $cursors,
            'command_signing_public_key_ed25519' => $signer->publicKeyBase64(),
            'signing_key_id' => $signer->signingKeyId(),
            'allowed_write_commands' => $allowedWriteCommands,
        ]);
    }

    public function pendingCommands(Request $request, RemoteCommandService $commandService): JsonResponse
    {
        /** @var ConnectorInstance $instance */
        $instance = $request->attributes->get('connector_instance');
        $source = $instance->dataSource;

        $commands = $commandService->pullPendingForAgent($source, $instance);

        return response()->json([
            'data_source_id' => $source->id,
            'commands' => $commands,
        ]);
    }

    public function ackCommand(Request $request, int $command, RemoteCommandService $commandService): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:executing,succeeded,failed'],
            'result' => ['nullable', 'array'],
            'error_code' => ['nullable', 'string', 'max:80'],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var ConnectorInstance $instance */
        $instance = $request->attributes->get('connector_instance');
        $source = $instance->dataSource;

        $commandModel = RemoteCommand::query()->findOrFail($command);
        $updated = $commandService->acknowledgeFromAgent($source, $instance, $commandModel, $validated);

        return response()->json([
            'command_id' => $updated->id,
            'status' => $updated->status,
            'reconciled_at' => $updated->reconciled_at?->utc()->toIso8601String(),
        ]);
    }

    public function ingestBatch(Request $request, SyncIngestionService $ingestionService): JsonResponse
    {
        $rawKey = $request->header('Idempotency-Key');
        if (! is_string($rawKey) || trim($rawKey) === '') {
            return response()->json(['message' => 'Idempotency-Key header is required.'], 422);
        }

        /** @var ConnectorInstance $instance */
        $instance = $request->attributes->get('connector_instance');
        $source = $instance->dataSource;

        $scope = "agent_batch:{$source->id}";
        $keyHash = hash('sha256', trim($rawKey));

        $existingKey = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key_hash', $keyHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingKey !== null) {
            $cached = $existingKey->response_body_json;
            $cached['idempotent_replay'] = true;

            return response()->json($cached, $existingKey->response_code);
        }

        $validated = $request->validate([
            'run_type' => ['nullable', 'in:full,incremental,manual'],
            'async' => ['nullable', 'boolean'],
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.event_id' => ['required', 'string', 'max:150'],
            'events.*.entity_type' => ['required', 'in:branch,warehouse,customer,product,inventory_balance,order'],
            'events.*.external_id' => ['required', 'string', 'max:120'],
            'events.*.payload' => ['required', 'array'],
            'events.*.cursor_table' => ['nullable', 'string', 'max:120'],
            'events.*.cursor_column' => ['nullable', 'string', 'max:120'],
            'events.*.cursor_value' => ['nullable', 'string', 'max:120'],
        ]);

        $instance->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        if (! empty($validated['async'])) {
            ProcessSyncBatchJob::dispatch(
                tenantId: (int) $source->tenant_id,
                dataSourceId: (int) $source->id,
                events: $validated['events'],
                connectorInstanceId: $instance->id,
                runType: $validated['run_type'] ?? 'incremental',
            );

            $body = [
                'status' => 'queued',
                'data_source_id' => $source->id,
                'records_received' => count($validated['events']),
                'idempotent_replay' => false,
            ];

            IdempotencyKey::query()->create([
                'scope' => $scope,
                'key_hash' => $keyHash,
                'response_code' => 202,
                'response_body_json' => $body,
                'expires_at' => now()->addDay(),
            ]);

            return response()->json($body, 202);
        }

        $run = $ingestionService->ingestBatch(
            source: $source,
            instance: $instance,
            events: $validated['events'],
            runType: $validated['run_type'] ?? 'incremental',
        );

        $body = [
            'sync_run_id' => $run->id,
            'data_source_id' => $source->id,
            'status' => $run->status,
            'records_received' => $run->records_received,
            'records_upserted' => $run->records_upserted,
            'records_skipped_duplicate' => $run->records_skipped_duplicate,
            'records_failed' => $run->records_failed,
            'freshness_status' => $source->fresh()->freshness_status,
            'idempotent_replay' => false,
        ];

        IdempotencyKey::query()->create([
            'scope' => $scope,
            'key_hash' => $keyHash,
            'response_code' => 200,
            'response_body_json' => $body,
            'expires_at' => now()->addDay(),
        ]);

        return response()->json($body, 200);
    }
}
