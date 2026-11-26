<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Payment;
use App\Domain\Orders\Models\Refund;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Sales\Models\ProductVariant;
use App\Domain\Tenants\Exceptions\MissingTenantContextException;
use App\Domain\Tenants\Services\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class OrderFinancialService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly InventoryLedgerService $inventoryLedger,
    ) {}

    /**
     * @param  list<array{
     *     product_id: int,
     *     variant_id?: int|null,
     *     quantity: string|int,
     *     discount_amount?: string|int,
     *     tax_amount?: string|int,
     *     unit_price?: string|int|null
     * }>  $lines
     */
    public function createOrder(
        int $branchId,
        array $lines,
        ?int $warehouseId = null,
        ?int $customerId = null,
        string $orderStatus = 'pending',
        string $currencyCode = 'SAR',
        bool $reserveStock = true,
        ?\DateTimeInterface $placedAt = null,
    ): Order {
        if ($lines === []) {
            throw new DomainException('An order must contain at least one item.');
        }

        $tenantId = $this->tenantContext->id();
        if ($tenantId === null) {
            throw MissingTenantContextException::forModel(Order::class);
        }

        return DB::transaction(function () use (
            $tenantId,
            $branchId,
            $warehouseId,
            $customerId,
            $lines,
            $orderStatus,
            $currencyCode,
            $reserveStock,
            $placedAt
        ): Order {
            $branch = Branch::query()->where('tenant_id', $tenantId)->findOrFail($branchId);

            $resolvedWarehouseId = $warehouseId;
            if ($resolvedWarehouseId === null) {
                $resolvedWarehouseId = Warehouse::query()
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branch->id)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->value('id');
            }

            $grossSales = DecimalMath::zero();
            $discountTotal = DecimalMath::zero();
            $taxTotal = DecimalMath::zero();
            $cogsTotal = DecimalMath::zero();
            $hasCompleteCostData = true;
            $preparedLines = [];

            foreach ($lines as $line) {
                $qty = DecimalMath::normalize($line['quantity']);
                if (! DecimalMath::gt($qty, '0')) {
                    throw new DomainException('Order line quantity must be positive.');
                }

                $product = Product::query()->where('tenant_id', $tenantId)->findOrFail((int) $line['product_id']);
                $variant = isset($line['variant_id']) && $line['variant_id'] !== null
                    ? ProductVariant::query()->where('tenant_id', $tenantId)->findOrFail((int) $line['variant_id'])
                    : null;

                $unitPrice = isset($line['unit_price']) && $line['unit_price'] !== null
                    ? DecimalMath::normalize($line['unit_price'])
                    : DecimalMath::normalize((string) ($variant?->unit_price ?? $product->base_price));

                $rawUnitCost = $variant?->cost_price ?? $product->cost_price;
                $unitCost = $rawUnitCost !== null ? DecimalMath::normalize((string) $rawUnitCost) : null;

                if ($unitCost === null) {
                    $hasCompleteCostData = false;
                } else {
                    $cogsTotal = DecimalMath::add($cogsTotal, DecimalMath::mul($qty, $unitCost));
                }

                $lineGross = DecimalMath::mul($qty, $unitPrice);
                $lineDiscount = DecimalMath::normalize($line['discount_amount'] ?? 0);
                $lineTax = DecimalMath::normalize($line['tax_amount'] ?? 0);
                $lineNet = DecimalMath::sub($lineGross, $lineDiscount);

                if (DecimalMath::lt($lineNet, '0')) {
                    throw new DomainException('Line discount cannot exceed gross amount.');
                }

                $lineTotal = DecimalMath::add($lineNet, $lineTax);

                $grossSales = DecimalMath::add($grossSales, $lineGross);
                $discountTotal = DecimalMath::add($discountTotal, $lineDiscount);
                $taxTotal = DecimalMath::add($taxTotal, $lineTax);

                $preparedLines[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'sku_snapshot' => $variant?->sku ?? $product->sku,
                    'product_name_snapshot' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                    'quantity' => $qty,
                    'unit_price_snapshot' => $unitPrice,
                    'unit_cost_snapshot' => $unitCost,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ];
            }

            $netSales = DecimalMath::sub($grossSales, $discountTotal);
            $payableTotal = DecimalMath::add($netSales, $taxTotal);
            $orderNumber = $this->numberGenerator->next($tenantId);

            $order = Order::query()->create([
                'branch_id' => $branch->id,
                'warehouse_id' => $resolvedWarehouseId,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'order_status' => $orderStatus,
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unfulfilled',
                'currency_code' => strtoupper($currencyCode),
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'net_sales' => $netSales,
                'paid_total' => DecimalMath::zero(),
                'refunded_total' => DecimalMath::zero(),
                'outstanding_total' => $payableTotal,
                'cogs_total' => $hasCompleteCostData ? $cogsTotal : null,
                'has_complete_cost_data' => $hasCompleteCostData,
                'placed_at' => $placedAt ?? now(),
            ]);

            foreach ($preparedLines as $index => $itemData) {
                OrderItem::query()->create(array_merge($itemData, [
                    'order_id' => $order->id,
                ]));

                if ($reserveStock && $resolvedWarehouseId !== null && in_array($orderStatus, ['pending', 'confirmed', 'processing'], true)) {
                    $this->inventoryLedger->reserveForOrder(
                        warehouseId: $resolvedWarehouseId,
                        productId: $itemData['product_id'],
                        orderId: $order->id,
                        quantity: $itemData['quantity'],
                        idempotencyKey: "order:{$order->id}:reserve:{$index}",
                        variantId: $itemData['variant_id'],
                        notes: "Reserved for {$order->order_number}",
                    );
                }
            }

            return $order->load(['items', 'reservations']);
        });
    }

    public function recordPayment(
        Order $order,
        string|int $amount,
        string $method,
        string $paymentReference,
        string $idempotencyKey,
        ?\DateTimeInterface $paidAt = null,
    ): Payment {
        $payAmount = DecimalMath::normalize($amount);
        if (! DecimalMath::gt($payAmount, '0')) {
            throw new DomainException('Payment amount must be strictly positive.');
        }

        return DB::transaction(function () use (
            $order,
            $payAmount,
            $method,
            $paymentReference,
            $idempotencyKey,
            $paidAt
        ): Payment {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $payment = Payment::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_reference' => $paymentReference,
                'method' => $method,
                'amount' => $payAmount,
                'currency_code' => $lockedOrder->currency_code,
                'status' => 'captured',
                'idempotency_key' => $idempotencyKey,
                'paid_at' => $paidAt ?? now(),
            ]);

            $newPaid = DecimalMath::add((string) $lockedOrder->paid_total, $payAmount);
            $payableTotal = DecimalMath::add((string) $lockedOrder->net_sales, (string) $lockedOrder->tax_total);
            $newOutstanding = DecimalMath::maxZero(DecimalMath::sub($payableTotal, $newPaid));

            $paymentStatus = $this->resolvePaymentStatus(
                payableTotal: $payableTotal,
                paidTotal: $newPaid,
                refundedTotal: (string) $lockedOrder->refunded_total,
            );

            $lockedOrder->forceFill([
                'paid_total' => $newPaid,
                'outstanding_total' => $newOutstanding,
                'payment_status' => $paymentStatus,
            ])->save();

            return $payment;
        });
    }

    public function recordRefund(
        Order $order,
        string|int $amount,
        string $reason,
        string $idempotencyKey,
        bool $restockInventory = false,
        ?int $paymentId = null,
        ?\DateTimeInterface $refundedAt = null,
    ): Refund {
        $refundAmount = DecimalMath::normalize($amount);
        if (! DecimalMath::gt($refundAmount, '0')) {
            throw new DomainException('Refund amount must be strictly positive.');
        }

        return DB::transaction(function () use (
            $order,
            $refundAmount,
            $reason,
            $idempotencyKey,
            $restockInventory,
            $paymentId,
            $refundedAt
        ): Refund {
            $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $newRefunded = DecimalMath::add((string) $lockedOrder->refunded_total, $refundAmount);

            if (DecimalMath::gt($newRefunded, (string) $lockedOrder->paid_total)) {
                throw new DomainException('Refund total cannot exceed paid amount.');
            }

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $paymentId,
                'amount' => $refundAmount,
                'reason' => $reason,
                'restock_inventory' => $restockInventory,
                'idempotency_key' => $idempotencyKey,
                'refunded_at' => $refundedAt ?? now(),
            ]);

            $payableTotal = DecimalMath::add((string) $lockedOrder->net_sales, (string) $lockedOrder->tax_total);
            $paymentStatus = $this->resolvePaymentStatus(
                payableTotal: $payableTotal,
                paidTotal: (string) $lockedOrder->paid_total,
                refundedTotal: $newRefunded,
            );

            $updates = [
                'refunded_total' => $newRefunded,
                'payment_status' => $paymentStatus,
            ];

            if ($restockInventory && $lockedOrder->warehouse_id !== null) {
                foreach ($lockedOrder->items as $index => $item) {
                    $this->inventoryLedger->restockFromRefund(
                        warehouseId: (int) $lockedOrder->warehouse_id,
                        productId: (int) $item->product_id,
                        refundId: $refund->id,
                        quantity: (string) $item->quantity,
                        idempotencyKey: "refund:{$refund->id}:restock:{$index}",
                        variantId: $item->variant_id !== null ? (int) $item->variant_id : null,
                        notes: "Restocked from refund #{$refund->id}",
                    );
                }
                $updates['fulfillment_status'] = 'returned';
            }

            $lockedOrder->forceFill($updates)->save();

            return $refund;
        });
    }

    private function resolvePaymentStatus(string $payableTotal, string $paidTotal, string $refundedTotal): string
    {
        if (DecimalMath::gt($refundedTotal, '0')) {
            return DecimalMath::gte($refundedTotal, $paidTotal) ? 'refunded' : 'partially_refunded';
        }

        if (! DecimalMath::gt($paidTotal, '0')) {
            return 'unpaid';
        }

        if (DecimalMath::lt($paidTotal, $payableTotal)) {
            return 'partial';
        }

        return 'paid';
    }
}
