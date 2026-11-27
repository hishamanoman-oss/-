<?php

declare(strict_types=1);

namespace App\Domain\Commands\Services;

use App\Application\Services\AuditLogger;
use App\Domain\Commands\Models\CommandApproval;
use App\Domain\Commands\Models\CommandAttempt;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Services\TenantContext;
use App\Infrastructure\Messaging\Events\RemoteCommandUpdated;
use App\Infrastructure\Messaging\Events\SyncBatchCompleted;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RemoteCommandService
{
    /** @var list<string> */
    public const ALLOWED_TYPES = [
        'product.price.update',
        'order.status.update',
        'inventory.stock.adjust',
    ];

    /** @var list<string> */
    public const ALLOWED_ORDER_STATUSES = [
        'completed',
        'partial',
        'unpaid',
        'refunded',
        'cancelled',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Ed25519CommandSigner $signer,
        private readonly AuditLogger $audit,
        private readonly InventoryLedgerService $inventoryLedger,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function requestCommand(
        User $actor,
        DataSource $source,
        string $commandType,
        int $targetInternalId,
        array $params,
        ?string $idempotencyKey = null,
        int $ttlMinutes = 30,
    ): RemoteCommand {
        if (! $this->tenantContext->hasPermission('commands.request')) {
            throw new DomainException('Missing required permission [commands.request].');
        }

        if ((int) $source->tenant_id !== $this->tenantContext->id()) {
            throw new DomainException('Data source does not belong to active tenant.');
        }

        if (! in_array($commandType, self::ALLOWED_TYPES, true)) {
            throw new DomainException("Command type [{$commandType}] is not in the allowed command catalog.");
        }

        $caps = is_array($source->capabilities_json) ? $source->capabilities_json : [];
        $allowedOnSource = is_array($caps['allowed_write_commands'] ?? null) ? $caps['allowed_write_commands'] : [];
        if (empty($caps['supports_write']) || ! in_array($commandType, $allowedOnSource, true)) {
            throw new DomainException("Data source [{$source->name}] does not permit write command [{$commandType}].");
        }

        $resolvedIdemKey = $idempotencyKey ? trim($idempotencyKey) : 'cmd-'.Str::uuid()->toString();
        $existing = RemoteCommand::query()
            ->where('idempotency_key', $resolvedIdemKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $resolved = $this->resolveTargetAndEvaluateRisk($source, $commandType, $targetInternalId, $params);
        $branchId = $resolved['branch_id'];

        if ($branchId !== null && ! $this->tenantContext->canAccessBranch($branchId)) {
            throw new DomainException("Actor is not authorized to issue commands for branch #{$branchId}.");
        }

        $initialStatus = $resolved['requires_approval'] ? 'pending' : 'approved';
        $expiresAt = CarbonImmutable::now()->addMinutes(max(1, min(1440, $ttlMinutes)));

        /** @var RemoteCommand $command */
        $command = DB::transaction(function () use (
            $source,
            $actor,
            $commandType,
            $resolved,
            $branchId,
            $initialStatus,
            $resolvedIdemKey,
            $expiresAt
        ): RemoteCommand {
            $cmd = RemoteCommand::query()->create([
                'data_source_id' => $source->id,
                'branch_id' => $branchId,
                'requested_by_user_id' => $actor->id,
                'command_type' => $commandType,
                'target_entity_type' => $resolved['entity_type'],
                'target_internal_id' => $resolved['internal_id'],
                'target_external_id' => $resolved['external_id'],
                'payload_json' => $resolved['payload'],
                'precondition_json' => $resolved['precondition'],
                'risk_level' => $resolved['risk_level'],
                'requires_approval' => $resolved['requires_approval'],
                'status' => $initialStatus,
                'idempotency_key' => $resolvedIdemKey,
                'signature_ed25519' => '',
                'signing_key_id' => $this->signer->signingKeyId(),
                'expires_at' => $expiresAt,
            ]);

            $signature = $this->signer->signEnvelope($cmd->toSignedEnvelope());
            $cmd->forceFill(['signature_ed25519' => $signature])->save();

            $this->audit->log(
                action: 'command.requested',
                entityType: 'remote_command',
                entityId: $cmd->id,
                metadata: [
                    'command_type' => $commandType,
                    'target_external_id' => $cmd->target_external_id,
                    'risk_level' => $cmd->risk_level,
                    'status' => $cmd->status,
                ],
                tenantId: (int) $cmd->tenant_id,
                actorUserId: $actor->id,
            );

            return $cmd->fresh();
        });

        event(new RemoteCommandUpdated(
            tenantId: (int) $command->tenant_id,
            commandId: (int) $command->id,
            commandType: $command->command_type,
            status: $command->status,
        ));

        return $command;
    }

    public function approve(RemoteCommand $command, User $approver, ?string $comment = null): RemoteCommand
    {
        if (! $this->tenantContext->hasPermission('commands.approve')) {
            throw new DomainException('Missing required permission [commands.approve].');
        }

        if ($command->branch_id !== null && ! $this->tenantContext->canAccessBranch((int) $command->branch_id)) {
            throw new DomainException('Approver is not authorized for the command branch.');
        }

        if ($command->status !== 'pending') {
            throw new DomainException("Only pending commands can be approved (current status: {$command->status}).");
        }

        if ($command->expires_at->isPast()) {
            $this->expireCommand($command);
            throw new DomainException('Command has already expired and cannot be approved.');
        }

        DB::transaction(function () use ($command, $approver, $comment): void {
            CommandApproval::query()->create([
                'remote_command_id' => $command->id,
                'approver_user_id' => $approver->id,
                'decision' => 'approved',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $command->forceFill(['status' => 'approved'])->save();

            $this->audit->log(
                action: 'command.approved',
                entityType: 'remote_command',
                entityId: $command->id,
                metadata: ['comment' => $comment],
                tenantId: (int) $command->tenant_id,
                actorUserId: $approver->id,
            );
        });

        event(new RemoteCommandUpdated(
            tenantId: (int) $command->tenant_id,
            commandId: (int) $command->id,
            commandType: $command->command_type,
            status: 'approved',
        ));

        return $command->fresh();
    }

    public function reject(RemoteCommand $command, User $approver, ?string $comment = null): RemoteCommand
    {
        if (! $this->tenantContext->hasPermission('commands.approve')) {
            throw new DomainException('Missing required permission [commands.approve].');
        }

        if ($command->branch_id !== null && ! $this->tenantContext->canAccessBranch((int) $command->branch_id)) {
            throw new DomainException('Approver is not authorized for the command branch.');
        }

        if ($command->status !== 'pending') {
            throw new DomainException("Only pending commands can be rejected (current status: {$command->status}).");
        }

        DB::transaction(function () use ($command, $approver, $comment): void {
            CommandApproval::query()->create([
                'remote_command_id' => $command->id,
                'approver_user_id' => $approver->id,
                'decision' => 'rejected',
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $command->forceFill([
                'status' => 'rejected',
                'error_code' => 'REJECTED_BY_APPROVER',
                'error_message' => $comment ?: 'Rejected by approver.',
            ])->save();

            $this->audit->log(
                action: 'command.rejected',
                entityType: 'remote_command',
                entityId: $command->id,
                metadata: ['comment' => $comment],
                tenantId: (int) $command->tenant_id,
                actorUserId: $approver->id,
            );
        });

        event(new RemoteCommandUpdated(
            tenantId: (int) $command->tenant_id,
            commandId: (int) $command->id,
            commandType: $command->command_type,
            status: 'rejected',
            errorCode: 'REJECTED_BY_APPROVER',
        ));

        return $command->fresh();
    }

    /**
     * Expire overdue approved/pending commands and dispatch valid approved commands for the connector.
     *
     * @return list<array<string, mixed>>
     */
    public function pullPendingForAgent(DataSource $source, ConnectorInstance $instance): array
    {
        $now = now();

        // Transition any expired pending/approved commands cleanly
        $expiredCommands = RemoteCommand::query()
            ->where('data_source_id', $source->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredCommands as $exp) {
            $this->expireCommand($exp);
        }

        $readyCommands = DB::transaction(function () use ($source, $instance, $now) {
            $commands = RemoteCommand::query()
                ->where('data_source_id', $source->id)
                ->where('status', 'approved')
                ->where('expires_at', '>', $now)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($commands as $cmd) {
                $attemptNo = ((int) CommandAttempt::query()->where('remote_command_id', $cmd->id)->max('attempt_number')) + 1;

                $cmd->forceFill([
                    'status' => 'dispatched',
                    'connector_instance_id' => $instance->id,
                    'dispatched_at' => $now,
                ])->save();

                CommandAttempt::query()->create([
                    'remote_command_id' => $cmd->id,
                    'connector_instance_id' => $instance->id,
                    'attempt_number' => $attemptNo,
                    'status' => 'dispatched',
                    'started_at' => $now,
                ]);

                $this->audit->log(
                    action: 'command.dispatched',
                    entityType: 'remote_command',
                    entityId: $cmd->id,
                    metadata: ['connector_instance_id' => $instance->id, 'attempt_number' => $attemptNo],
                    tenantId: (int) $cmd->tenant_id,
                    actorUserId: null,
                );
            }

            return $commands;
        });

        $envelopes = [];
        foreach ($readyCommands as $cmd) {
            event(new RemoteCommandUpdated(
                tenantId: (int) $cmd->tenant_id,
                commandId: (int) $cmd->id,
                commandType: $cmd->command_type,
                status: 'dispatched',
            ));

            $envelopes[] = array_merge($cmd->toSignedEnvelope(), [
                'signature_ed25519' => $cmd->signature_ed25519,
                'signing_key_id' => $cmd->signing_key_id,
            ]);
        }

        return $envelopes;
    }

    /**
     * Process execution acknowledgement from the Local Sync Agent and reconcile canonical state on success.
     *
     * @param  array{status: string, result?: array<string, mixed>|null, error_code?: string|null, error_message?: string|null}  $ack
     */
    public function acknowledgeFromAgent(
        DataSource $source,
        ConnectorInstance $instance,
        RemoteCommand $command,
        array $ack,
    ): RemoteCommand {
        if ((int) $command->data_source_id !== (int) $source->id) {
            throw new DomainException('Command does not belong to the authenticated data source.');
        }

        $ackStatus = $ack['status'];
        $now = now();
        $resultData = is_array($ack['result'] ?? null) ? $ack['result'] : null;
        $errCode = isset($ack['error_code']) ? (string) $ack['error_code'] : null;
        $errMsg = isset($ack['error_message']) ? (string) $ack['error_message'] : null;

        if ($ackStatus === 'executing') {
            $command->forceFill(['status' => 'executing'])->save();
            $this->updateLatestAttempt($command, $instance, 'executing', null, null, null, false);

            $this->audit->log(
                action: 'command.executing',
                entityType: 'remote_command',
                entityId: $command->id,
                metadata: ['connector_instance_id' => $instance->id],
                tenantId: (int) $command->tenant_id,
                actorUserId: null,
            );

            event(new RemoteCommandUpdated(
                tenantId: (int) $command->tenant_id,
                commandId: (int) $command->id,
                commandType: $command->command_type,
                status: 'executing',
            ));

            return $command->fresh();
        }

        if ($ackStatus === 'failed') {
            $command->forceFill([
                'status' => 'failed',
                'acknowledged_at' => $now,
                'result_json' => $resultData,
                'error_code' => $errCode ?: 'EXECUTION_FAILED',
                'error_message' => $errMsg ?: 'Agent reported execution failure.',
            ])->save();

            $this->updateLatestAttempt($command, $instance, 'failed', $resultData, $command->error_code, $command->error_message, true);

            $this->audit->log(
                action: 'command.failed',
                entityType: 'remote_command',
                entityId: $command->id,
                metadata: ['error_code' => $command->error_code, 'error_message' => $command->error_message],
                tenantId: (int) $command->tenant_id,
                actorUserId: null,
            );

            event(new RemoteCommandUpdated(
                tenantId: (int) $command->tenant_id,
                commandId: (int) $command->id,
                commandType: $command->command_type,
                status: 'failed',
                errorCode: $command->error_code,
            ));

            return $command->fresh();
        }

        // Succeeded execution path: awaiting_reconciliation -> reconcile canonical state -> succeeded
        DB::transaction(function () use ($command, $instance, $resultData, $now): void {
            $command->forceFill([
                'status' => 'awaiting_reconciliation',
                'acknowledged_at' => $now,
                'result_json' => $resultData,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $this->reconcileCanonicalState($command, $resultData);

            $command->forceFill([
                'status' => 'succeeded',
                'reconciled_at' => now(),
            ])->save();

            $this->updateLatestAttempt($command, $instance, 'succeeded', $resultData, null, null, true);

            $this->audit->log(
                action: 'command.succeeded',
                entityType: 'remote_command',
                entityId: $command->id,
                metadata: [
                    'command_type' => $command->command_type,
                    'target_external_id' => $command->target_external_id,
                    'result' => $resultData,
                ],
                tenantId: (int) $command->tenant_id,
                actorUserId: null,
            );
        });

        event(new RemoteCommandUpdated(
            tenantId: (int) $command->tenant_id,
            commandId: (int) $command->id,
            commandType: $command->command_type,
            status: 'succeeded',
        ));

        event(new SyncBatchCompleted(
            tenantId: (int) $command->tenant_id,
            dataSourceId: (int) $source->id,
            syncRunId: 0,
            status: 'succeeded',
            recordsUpserted: 1,
            freshnessStatus: $source->fresh()->freshness_status,
        ));

        return $command->fresh();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *     entity_type: string,
     *     internal_id: int,
     *     external_id: string,
     *     branch_id: int|null,
     *     payload: array<string, mixed>,
     *     precondition: array<string, mixed>,
     *     risk_level: string,
     *     requires_approval: bool
     * }
     */
    private function resolveTargetAndEvaluateRisk(
        DataSource $source,
        string $commandType,
        int $targetInternalId,
        array $params,
    ): array {
        $reason = isset($params['reason']) ? trim((string) $params['reason']) : 'Remote dashboard operation';

        if ($commandType === 'product.price.update') {
            $product = Product::query()->findOrFail($targetInternalId);
            $externalId = $this->requireExternalMapping($source, 'product', $product->id);

            $newPriceRaw = (string) ($params['new_unit_price'] ?? '');
            $newPrice = DecimalMath::normalize($newPriceRaw);
            if (DecimalMath::lte($newPrice, '0')) {
                throw new DomainException('New unit price must be greater than zero.');
            }

            $currentPrice = DecimalMath::normalize((string) $product->base_price);
            $diff = DecimalMath::gt($newPrice, $currentPrice)
                ? DecimalMath::sub($newPrice, $currentPrice)
                : DecimalMath::sub($currentPrice, $newPrice);

            $isHighRisk = DecimalMath::lte($currentPrice, '0')
                || DecimalMath::gt(DecimalMath::mul(DecimalMath::div($diff, $currentPrice), '100'), '15');

            return [
                'entity_type' => 'product',
                'internal_id' => $product->id,
                'external_id' => $externalId,
                'branch_id' => null,
                'payload' => [
                    'new_unit_price' => $newPrice,
                    'reason' => $reason,
                ],
                'precondition' => [
                    'unit_price' => $currentPrice,
                ],
                'risk_level' => $isHighRisk ? 'high' : 'low',
                'requires_approval' => $isHighRisk,
            ];
        }

        if ($commandType === 'order.status.update') {
            $order = Order::query()->findOrFail($targetInternalId);
            $externalId = $this->requireExternalMapping($source, 'order', $order->id);

            $newStatus = strtolower(trim((string) ($params['new_inv_status'] ?? '')));
            if (! in_array($newStatus, self::ALLOWED_ORDER_STATUSES, true)) {
                throw new DomainException("Unsupported POS invoice status [{$newStatus}].");
            }

            $currentPosStatus = match (true) {
                $order->order_status === 'cancelled' => 'cancelled',
                $order->payment_status === 'refunded' => 'refunded',
                $order->payment_status === 'partial' => 'partial',
                $order->payment_status === 'unpaid' => 'unpaid',
                default => 'completed',
            };

            $isHighRisk = in_array($newStatus, ['cancelled', 'refunded'], true);

            return [
                'entity_type' => 'order',
                'internal_id' => $order->id,
                'external_id' => $externalId,
                'branch_id' => (int) $order->branch_id,
                'payload' => [
                    'new_inv_status' => $newStatus,
                    'reason' => $reason,
                ],
                'precondition' => [
                    'inv_status' => $currentPosStatus,
                ],
                'risk_level' => $isHighRisk ? 'high' : 'low',
                'requires_approval' => $isHighRisk,
            ];
        }

        // inventory.stock.adjust
        $balance = InventoryBalance::query()->with('warehouse')->findOrFail($targetInternalId);
        $externalId = $this->requireExternalMapping($source, 'inventory_balance', $balance->id);

        $delta = DecimalMath::normalize((string) ($params['delta'] ?? '0'));
        if (DecimalMath::comp($delta, '0') === 0) {
            throw new DomainException('Stock adjustment delta cannot be zero.');
        }

        $currentOnHand = DecimalMath::normalize((string) $balance->on_hand);
        $targetOnHand = DecimalMath::add($currentOnHand, $delta);
        if (DecimalMath::lt($targetOnHand, (string) $balance->reserved)) {
            throw new DomainException('Adjusted on-hand quantity cannot fall below reserved units.');
        }

        return [
            'entity_type' => 'inventory_balance',
            'internal_id' => $balance->id,
            'external_id' => $externalId,
            'branch_id' => $balance->warehouse ? (int) $balance->warehouse->branch_id : null,
            'payload' => [
                'delta' => $delta,
                'new_qty_on_hand' => $targetOnHand,
                'reason' => $reason,
            ],
            'precondition' => [
                'qty_on_hand' => $currentOnHand,
            ],
            'risk_level' => 'high',
            'requires_approval' => true,
        ];
    }

    private function requireExternalMapping(DataSource $source, string $entity, int $internalId): string
    {
        $ext = ExternalMapping::query()
            ->where('data_source_id', $source->id)
            ->where('canonical_entity', $entity)
            ->where('internal_id', $internalId)
            ->value('external_id');

        if (! is_string($ext) || $ext === '') {
            throw new DomainException("Target {$entity} #{$internalId} is not mapped to external source [{$source->name}]. Sync from source first.");
        }

        return $ext;
    }

    /**
     * @param  array<string, mixed>|null  $resultData
     */
    private function reconcileCanonicalState(RemoteCommand $command, ?array $resultData): void
    {
        if ($command->command_type === 'product.price.update') {
            $product = Product::query()->findOrFail($command->target_internal_id);
            $confirmedPrice = (string) ($resultData['confirmed_unit_price'] ?? $command->payload_json['new_unit_price']);
            $product->forceFill([
                'base_price' => DecimalMath::normalize($confirmedPrice),
                'source_updated_at' => now(),
            ])->save();

            return;
        }

        if ($command->command_type === 'order.status.update') {
            $order = Order::query()->findOrFail($command->target_internal_id);
            $newPosStatus = (string) ($resultData['confirmed_inv_status'] ?? $command->payload_json['new_inv_status']);
            [$orderStatus, $paymentStatus, $fulfillmentStatus] = match ($newPosStatus) {
                'partial' => ['confirmed', 'partial', 'fulfilled'],
                'unpaid' => ['confirmed', 'unpaid', 'unfulfilled'],
                'refunded' => ['completed', 'refunded', 'returned'],
                'cancelled' => ['cancelled', 'unpaid', 'unfulfilled'],
                default => ['completed', 'paid', 'fulfilled'],
            };

            $order->forceFill([
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus,
                'source_updated_at' => now(),
            ])->save();

            return;
        }

        if ($command->command_type === 'inventory.stock.adjust') {
            $balance = InventoryBalance::query()->findOrFail($command->target_internal_id);
            $confirmedOnHand = (string) ($resultData['confirmed_qty_on_hand'] ?? $command->payload_json['new_qty_on_hand']);

            $this->inventoryLedger->reconcileSyncedBalance(
                warehouseId: (int) $balance->warehouse_id,
                productId: (int) $balance->product_id,
                targetOnHand: DecimalMath::normalize($confirmedOnHand),
                targetReserved: (string) $balance->reserved,
                idempotencyKey: "cmd-reconcile:{$command->id}:{$command->idempotency_key}",
                notes: "Remote command #{$command->id} reconciled",
            );
        }
    }

    private function expireCommand(RemoteCommand $command): void
    {
        $command->forceFill([
            'status' => 'expired',
            'error_code' => 'COMMAND_EXPIRED',
            'error_message' => 'Command TTL expired before execution.',
        ])->save();

        $this->audit->log(
            action: 'command.expired',
            entityType: 'remote_command',
            entityId: $command->id,
            metadata: ['expires_at' => $command->expires_at?->toIso8601String()],
            tenantId: (int) $command->tenant_id,
            actorUserId: null,
        );

        event(new RemoteCommandUpdated(
            tenantId: (int) $command->tenant_id,
            commandId: (int) $command->id,
            commandType: $command->command_type,
            status: 'expired',
            errorCode: 'COMMAND_EXPIRED',
        ));
    }

    /**
     * @param  array<string, mixed>|null  $responseJson
     */
    private function updateLatestAttempt(
        RemoteCommand $command,
        ConnectorInstance $instance,
        string $status,
        ?array $responseJson,
        ?string $errorCode,
        ?string $errorMessage,
        bool $finished,
    ): void {
        $attempt = CommandAttempt::query()
            ->where('remote_command_id', $command->id)
            ->latest('attempt_number')
            ->first();

        if ($attempt === null) {
            CommandAttempt::query()->create([
                'remote_command_id' => $command->id,
                'connector_instance_id' => $instance->id,
                'attempt_number' => 1,
                'status' => $status,
                'agent_response_json' => $responseJson,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'started_at' => now(),
                'finished_at' => $finished ? now() : null,
            ]);

            return;
        }

        $attempt->forceFill([
            'status' => $status,
            'agent_response_json' => $responseJson ?? $attempt->agent_response_json,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'finished_at' => $finished ? now() : $attempt->finished_at,
        ])->save();
    }
}
