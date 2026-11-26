<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Application\Services\AuditLogger;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\StockTransferItem;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Exceptions\MissingTenantContextException;
use App\Domain\Tenants\Services\TenantContext;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InventoryLedgerService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function recordOpeningOrReceipt(
        int $warehouseId,
        int $productId,
        string|int $quantity,
        string $idempotencyKey,
        string $movementType = 'receipt',
        ?int $variantId = null,
        ?string $referenceType = null,
        string|int|null $referenceId = null,
        ?string $notes = null,
    ): StockMovement {
        if (! in_array($movementType, ['opening', 'receipt'], true)) {
            throw new DomainException("Unsupported opening/receipt movement type [{$movementType}].");
        }

        $qty = DecimalMath::normalize($quantity);
        if (! DecimalMath::gt($qty, '0')) {
            throw new DomainException('Receipt/opening quantity must be strictly positive.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $variantId,
            $qty,
            $movementType,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $notes
        ): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $balance = $this->lockOrCreateBalance($warehouseId, $productId, $variantId);
            $newOnHand = DecimalMath::add((string) $balance->on_hand, $qty);

            return $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: $newOnHand,
                newReserved: (string) $balance->reserved,
                movementType: $movementType,
                quantityDelta: $qty,
                idempotencyKey: $idempotencyKey,
                referenceType: $referenceType,
                referenceId: $referenceId,
                notes: $notes,
            );
        });
    }

    public function reserveForOrder(
        int $warehouseId,
        int $productId,
        int $orderId,
        string|int $quantity,
        string $idempotencyKey,
        ?int $variantId = null,
        ?string $notes = null,
    ): StockMovement {
        $qty = DecimalMath::normalize($quantity);
        if (! DecimalMath::gt($qty, '0')) {
            throw new DomainException('Reservation quantity must be strictly positive.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $variantId,
            $orderId,
            $qty,
            $idempotencyKey,
            $notes
        ): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $balance = $this->lockOrCreateBalance($warehouseId, $productId, $variantId);
            $newReserved = DecimalMath::add((string) $balance->reserved, $qty);
            $newAvailable = DecimalMath::sub((string) $balance->on_hand, $newReserved);

            if (DecimalMath::lt($newAvailable, '0')) {
                throw new DomainException('Insufficient available inventory to reserve.');
            }

            StockReservation::query()->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'order_id' => $orderId,
                'quantity' => $qty,
                'status' => 'active',
            ]);

            return $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: (string) $balance->on_hand,
                newReserved: $newReserved,
                movementType: 'reservation',
                quantityDelta: DecimalMath::sub('0', $qty),
                idempotencyKey: $idempotencyKey,
                referenceType: 'order',
                referenceId: $orderId,
                notes: $notes ?? 'Order stock reservation',
                balanceAfterOverride: $newAvailable,
            );
        });
    }

    public function releaseReservation(
        StockReservation $reservation,
        string $idempotencyKey,
        ?string $notes = null,
    ): StockMovement {
        if ($reservation->status !== 'active') {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            throw new DomainException('Only active stock reservations can be released.');
        }

        return DB::transaction(function () use ($reservation, $idempotencyKey, $notes): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $qty = DecimalMath::normalize((string) $reservation->quantity);
            $balance = $this->lockOrCreateBalance(
                $reservation->warehouse_id,
                $reservation->product_id,
                $reservation->variant_id
            );

            $newReserved = DecimalMath::sub((string) $balance->reserved, $qty);
            if (DecimalMath::lt($newReserved, '0')) {
                throw new DomainException('Reserved inventory cannot drop below zero.');
            }

            $reservation->update(['status' => 'released']);

            $newAvailable = DecimalMath::sub((string) $balance->on_hand, $newReserved);

            return $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: (string) $balance->on_hand,
                newReserved: $newReserved,
                movementType: 'release_reservation',
                quantityDelta: $qty,
                idempotencyKey: $idempotencyKey,
                referenceType: 'order',
                referenceId: $reservation->order_id,
                notes: $notes ?? 'Released order reservation',
                balanceAfterOverride: $newAvailable,
            );
        });
    }

    public function fulfillReservation(
        StockReservation $reservation,
        string $idempotencyKey,
        ?string $notes = null,
    ): StockMovement {
        if ($reservation->status !== 'active') {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            throw new DomainException('Only active stock reservations can be fulfilled.');
        }

        return DB::transaction(function () use ($reservation, $idempotencyKey, $notes): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $qty = DecimalMath::normalize((string) $reservation->quantity);
            $balance = $this->lockOrCreateBalance(
                $reservation->warehouse_id,
                $reservation->product_id,
                $reservation->variant_id
            );

            $newOnHand = DecimalMath::sub((string) $balance->on_hand, $qty);
            $newReserved = DecimalMath::sub((string) $balance->reserved, $qty);

            if (DecimalMath::lt($newOnHand, '0') || DecimalMath::lt($newReserved, '0')) {
                throw new DomainException('Cannot fulfill reservation below zero balance.');
            }

            $reservation->update(['status' => 'fulfilled']);

            return $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: $newOnHand,
                newReserved: $newReserved,
                movementType: 'sale_deduction',
                quantityDelta: DecimalMath::sub('0', $qty),
                idempotencyKey: $idempotencyKey,
                referenceType: 'order',
                referenceId: $reservation->order_id,
                notes: $notes ?? 'Fulfilled order reservation',
            );
        });
    }

    public function adjustStock(
        int $warehouseId,
        int $productId,
        string|int $quantityDelta,
        string $idempotencyKey,
        ?int $variantId = null,
        ?string $notes = null,
    ): StockMovement {
        $delta = DecimalMath::normalize($quantityDelta);
        if (DecimalMath::eq($delta, '0')) {
            throw new DomainException('Adjustment delta cannot be zero.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $variantId,
            $delta,
            $idempotencyKey,
            $notes
        ): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $balance = $this->lockOrCreateBalance($warehouseId, $productId, $variantId);
            $newOnHand = DecimalMath::add((string) $balance->on_hand, $delta);

            $movement = $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: $newOnHand,
                newReserved: (string) $balance->reserved,
                movementType: 'adjustment',
                quantityDelta: $delta,
                idempotencyKey: $idempotencyKey,
                referenceType: 'manual_adjustment',
                referenceId: $warehouseId,
                notes: $notes,
            );

            $this->auditLogger->log('inventory.adjusted', 'inventory_balance', $balance->id, [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'delta' => $delta,
                'on_hand_after' => $newOnHand,
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    public function restockFromRefund(
        int $warehouseId,
        int $productId,
        int $refundId,
        string|int $quantity,
        string $idempotencyKey,
        ?int $variantId = null,
        ?string $notes = null,
    ): StockMovement {
        $qty = DecimalMath::normalize($quantity);
        if (! DecimalMath::gt($qty, '0')) {
            throw new DomainException('Restock quantity must be strictly positive.');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $variantId,
            $refundId,
            $qty,
            $idempotencyKey,
            $notes
        ): StockMovement {
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }

            $balance = $this->lockOrCreateBalance($warehouseId, $productId, $variantId);
            $newOnHand = DecimalMath::add((string) $balance->on_hand, $qty);

            return $this->commitBalanceAndMovement(
                balance: $balance,
                newOnHand: $newOnHand,
                newReserved: (string) $balance->reserved,
                movementType: 'return_restock',
                quantityDelta: $qty,
                idempotencyKey: $idempotencyKey,
                referenceType: 'refund',
                referenceId: $refundId,
                notes: $notes ?? 'Refund inventory restock',
            );
        });
    }

    public function transferBetweenWarehouses(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        string|int $quantity,
        string $idempotencyKey,
        ?int $variantId = null,
        ?string $notes = null,
    ): StockTransfer {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new DomainException('Source and destination warehouses must be different.');
        }

        $qty = DecimalMath::normalize($quantity);
        if (! DecimalMath::gt($qty, '0')) {
            throw new DomainException('Transfer quantity must be strictly positive.');
        }

        return DB::transaction(function () use (
            $fromWarehouseId,
            $toWarehouseId,
            $productId,
            $variantId,
            $qty,
            $idempotencyKey,
            $notes
        ): StockTransfer {
            $tenantId = $this->requireTenantId();

            $existingOut = $this->findExistingMovement("{$idempotencyKey}:out");
            if ($existingOut !== null && $existingOut->reference_type === 'stock_transfer') {
                return StockTransfer::query()->findOrFail((int) $existingOut->reference_id);
            }

            $fromWarehouse = Warehouse::query()->where('tenant_id', $tenantId)->find($fromWarehouseId);
            $toWarehouse = Warehouse::query()->where('tenant_id', $tenantId)->find($toWarehouseId);

            if ($fromWarehouse === null || $toWarehouse === null) {
                throw new DomainException('Both warehouses must belong to the active tenant.');
            }

            $transfer = StockTransfer::query()->create([
                'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => 'completed',
                'requested_by_user_id' => Auth::id(),
                'completed_at' => now(),
                'notes' => $notes,
            ]);

            StockTransferItem::query()->create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $qty,
            ]);

            // Deterministic lock ordering by warehouse_id to prevent deadlocks
            if ($fromWarehouseId < $toWarehouseId) {
                $fromBalance = $this->lockOrCreateBalance($fromWarehouseId, $productId, $variantId);
                $toBalance = $this->lockOrCreateBalance($toWarehouseId, $productId, $variantId);
            } else {
                $toBalance = $this->lockOrCreateBalance($toWarehouseId, $productId, $variantId);
                $fromBalance = $this->lockOrCreateBalance($fromWarehouseId, $productId, $variantId);
            }

            $newFromOnHand = DecimalMath::sub((string) $fromBalance->on_hand, $qty);
            $this->commitBalanceAndMovement(
                balance: $fromBalance,
                newOnHand: $newFromOnHand,
                newReserved: (string) $fromBalance->reserved,
                movementType: 'transfer_out',
                quantityDelta: DecimalMath::sub('0', $qty),
                idempotencyKey: "{$idempotencyKey}:out",
                referenceType: 'stock_transfer',
                referenceId: $transfer->id,
                notes: $notes ?? "Transfer to {$toWarehouse->code}",
            );

            $newToOnHand = DecimalMath::add((string) $toBalance->on_hand, $qty);
            $this->commitBalanceAndMovement(
                balance: $toBalance,
                newOnHand: $newToOnHand,
                newReserved: (string) $toBalance->reserved,
                movementType: 'transfer_in',
                quantityDelta: $qty,
                idempotencyKey: "{$idempotencyKey}:in",
                referenceType: 'stock_transfer',
                referenceId: $transfer->id,
                notes: $notes ?? "Transfer from {$fromWarehouse->code}",
            );

            $this->auditLogger->log('inventory.transferred', 'stock_transfer', $transfer->id, [
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'product_id' => $productId,
                'quantity' => $qty,
            ]);

            return $transfer->load('items');
        });
    }

    public function reconcileSyncedBalance(
        int $warehouseId,
        int $productId,
        string|int $targetOnHand,
        string|int $targetReserved,
        string $idempotencyKey,
        ?int $variantId = null,
        ?string $notes = null,
    ): InventoryBalance {
        $onHand = DecimalMath::normalize($targetOnHand);
        $reserved = DecimalMath::normalize($targetReserved);

        return DB::transaction(function () use (
            $warehouseId,
            $productId,
            $variantId,
            $onHand,
            $reserved,
            $idempotencyKey,
            $notes
        ): InventoryBalance {
            $balance = $this->lockOrCreateBalance($warehouseId, $productId, $variantId);
            $existing = $this->findExistingMovement($idempotencyKey);
            if ($existing !== null) {
                return $balance;
            }

            $deltaOnHand = DecimalMath::sub($onHand, (string) $balance->on_hand);
            if (! DecimalMath::eq($deltaOnHand, '0')) {
                $movementType = DecimalMath::eq((string) $balance->on_hand, '0') && DecimalMath::gt($deltaOnHand, '0')
                    ? 'receipt'
                    : 'adjustment';

                $this->commitBalanceAndMovement(
                    balance: $balance,
                    newOnHand: $onHand,
                    newReserved: $reserved,
                    movementType: $movementType,
                    quantityDelta: $deltaOnHand,
                    idempotencyKey: $idempotencyKey,
                    referenceType: 'external_sync',
                    referenceId: $warehouseId,
                    notes: $notes ?? 'External source inventory sync',
                );

                return $balance->fresh();
            }

            $newAvailable = DecimalMath::sub((string) $balance->on_hand, $reserved);
            if (DecimalMath::lt($newAvailable, '0')) {
                throw new DomainException('Available inventory cannot become negative during sync reconciliation.');
            }

            $balance->forceFill([
                'reserved' => $reserved,
                'available' => $newAvailable,
                'version' => $balance->version + 1,
                'updated_at' => now(),
            ])->save();

            return $balance;
        });
    }

    private function findExistingMovement(string $idempotencyKey): ?StockMovement
    {
        return StockMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function lockOrCreateBalance(int $warehouseId, int $productId, ?int $variantId): InventoryBalance
    {
        $tenantId = $this->requireTenantId();

        $query = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId);

        if ($variantId === null) {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
        }

        $balance = $query->lockForUpdate()->first();
        if ($balance !== null) {
            return $balance;
        }

        return InventoryBalance::query()->create([
            'tenant_id' => $tenantId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'on_hand' => DecimalMath::zero(),
            'reserved' => DecimalMath::zero(),
            'available' => DecimalMath::zero(),
            'version' => 1,
            'updated_at' => now(),
        ]);
    }

    private function commitBalanceAndMovement(
        InventoryBalance $balance,
        string $newOnHand,
        string $newReserved,
        string $movementType,
        string $quantityDelta,
        string $idempotencyKey,
        ?string $referenceType,
        string|int|null $referenceId,
        ?string $notes,
        ?string $balanceAfterOverride = null,
    ): StockMovement {
        $newAvailable = DecimalMath::sub($newOnHand, $newReserved);

        if (DecimalMath::lt($newOnHand, '0')) {
            throw new DomainException('On-hand inventory cannot become negative.');
        }

        if (DecimalMath::lt($newReserved, '0')) {
            throw new DomainException('Reserved inventory cannot become negative.');
        }

        if (DecimalMath::lt($newAvailable, '0')) {
            throw new DomainException('Available inventory cannot become negative (overselling blocked).');
        }

        $balance->forceFill([
            'on_hand' => $newOnHand,
            'reserved' => $newReserved,
            'available' => $newAvailable,
            'version' => $balance->version + 1,
            'updated_at' => now(),
        ])->save();

        return StockMovement::query()->create([
            'tenant_id' => $balance->tenant_id,
            'warehouse_id' => $balance->warehouse_id,
            'product_id' => $balance->product_id,
            'variant_id' => $balance->variant_id,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'balance_after' => $balanceAfterOverride ?? $newOnHand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId !== null ? (string) $referenceId : null,
            'idempotency_key' => $idempotencyKey,
            'actor_user_id' => Auth::id(),
            'notes' => $notes,
            'occurred_at' => now(),
        ]);
    }

    private function requireTenantId(): int
    {
        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            throw MissingTenantContextException::forModel(InventoryBalance::class);
        }

        return $tenantId;
    }
}
