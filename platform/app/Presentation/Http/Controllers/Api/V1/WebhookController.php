<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Domain\Integrations\Models\DataSource;
use App\Domain\Synchronization\Models\WebhookEvent;
use App\Domain\Synchronization\Services\SyncIngestionService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __invoke(
        Request $request,
        int $dataSource,
        TenantContext $tenantContext,
        SyncIngestionService $ingestionService,
    ): JsonResponse {
        /** @var DataSource|null $source */
        $source = $tenantContext->runWithoutTenantScope(fn () => DataSource::query()->find($dataSource));

        if ($source === null) {
            return response()->json(['message' => 'Data source not found.'], 404);
        }

        $tenantContext->setTenantId((int) $source->tenant_id);

        try {
            $deliveryId = $request->header('X-Webhook-Delivery-Id');
            if (! is_string($deliveryId) || trim($deliveryId) === '') {
                return response()->json(['message' => 'X-Webhook-Delivery-Id header is required.'], 422);
            }
            $deliveryId = trim($deliveryId);

            $cfg = is_array($source->config_encrypted) ? $source->config_encrypted : [];
            $secret = (string) ($cfg['webhook_secret'] ?? '');
            $providedSig = (string) $request->header('X-Webhook-Signature', '');
            $normalizedProvided = str_starts_with($providedSig, 'sha256=')
                ? substr($providedSig, 7)
                : $providedSig;

            $expectedSig = hash_hmac('sha256', $request->getContent(), $secret);
            $signatureValid = $secret !== '' && $normalizedProvided !== '' && hash_equals($expectedSig, $normalizedProvided);

            $payload = json_decode($request->getContent(), true);
            if (! is_array($payload)) {
                $payload = $request->all();
            }

            $existing = WebhookEvent::query()
                ->where('data_source_id', $source->id)
                ->where('delivery_id', $deliveryId)
                ->first();

            if (! $signatureValid) {
                if ($existing === null) {
                    WebhookEvent::query()->create([
                        'data_source_id' => $source->id,
                        'provider' => $source->connector_type,
                        'delivery_id' => $deliveryId,
                        'signature_valid' => false,
                        'payload_json' => $payload,
                        'status' => 'rejected',
                        'received_at' => now(),
                    ]);
                }

                return response()->json(['message' => 'Invalid webhook signature.'], 401);
            }

            if ($existing !== null) {
                return response()->json([
                    'status' => 'duplicate',
                    'delivery_id' => $deliveryId,
                ], 200);
            }

            $webhookEvent = WebhookEvent::query()->create([
                'data_source_id' => $source->id,
                'provider' => $source->connector_type,
                'delivery_id' => $deliveryId,
                'signature_valid' => true,
                'payload_json' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]);

            if (isset($payload['events']) && is_array($payload['events']) && count($payload['events']) > 0) {
                $ingestionService->ingestBatch(
                    source: $source,
                    instance: null,
                    events: $payload['events'],
                    runType: 'incremental',
                );
            }

            $webhookEvent->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();

            return response()->json([
                'status' => 'processed',
                'delivery_id' => $deliveryId,
                'webhook_event_id' => $webhookEvent->id,
            ], 200);
        } finally {
            $tenantContext->clear();
        }
    }
}
