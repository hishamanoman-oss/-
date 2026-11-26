<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Services;

use App\Domain\Customers\Models\Customer;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\ConnectorInstance;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Integrations\Models\FieldMapping;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Payment;
use App\Domain\Orders\Models\Refund;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Category;
use App\Domain\Sales\Models\Product;
use App\Domain\Synchronization\Models\SyncCursor;
use App\Domain\Synchronization\Models\SyncError;
use App\Domain\Synchronization\Models\SyncEvent;
use App\Domain\Synchronization\Models\SyncRun;
use App\Infrastructure\Connectors\LocalMysqlReadOnlyConnector;
use App\Infrastructure\Messaging\Events\DataFreshnessUpdated;
use App\Infrastructure\Messaging\Events\SyncBatchCompleted;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncIngestionService
{
    public function __construct(
        private readonly CanonicalNormalizer $normalizer,
        private readonly InventoryLedgerService $inventoryLedger,
        private readonly FreshnessEvaluator $freshnessEvaluator,
    ) {}

    public function syncFromConnector(
        DataSource $source,
        LocalMysqlReadOnlyConnector $connector,
        string $runType = 'manual',
    ): SyncRun {
        $source->forceFill(['freshness_status' => 'syncing'])->save();

        $mappings = FieldMapping::query()->where('data_source_id', $source->id)->get();
        $events = [];
        $cursorUpdates = [];

        $branches = $connector->fetchAllFromTable('tbl_branches', 'br_id');
        foreach ($branches as $row) {
            $extId = (string) $row['br_id'];
            $events[] = [
                'event_id' => "branch:{$extId}:".md5((string) json_encode($row)),
                'entity_type' => 'branch',
                'external_id' => $extId,
                'payload' => $row,
            ];
            $cursorUpdates['tbl_branches'] = ['cursor_column' => 'br_id', 'value' => $extId];
        }

        $warehouses = $connector->fetchAllFromTable('tbl_warehouses', 'wh_id');
        foreach ($warehouses as $row) {
            $extId = (string) $row['wh_id'];
            $events[] = [
                'event_id' => "warehouse:{$extId}:".md5((string) json_encode($row)),
                'entity_type' => 'warehouse',
                'external_id' => $extId,
                'payload' => $row,
            ];
            $cursorUpdates['tbl_warehouses'] = ['cursor_column' => 'wh_id', 'value' => $extId];
        }

        $clients = $connector->fetchAllFromTable('tbl_clients', 'updated_at');
        foreach ($clients as $row) {
            $extId = (string) $row['client_no'];
            $events[] = [
                'event_id' => "customer:{$extId}:".md5((string) json_encode($row)),
                'entity_type' => 'customer',
                'external_id' => $extId,
                'payload' => $row,
            ];
            $cursorUpdates['tbl_clients'] = ['cursor_column' => 'updated_at', 'value' => (string) ($row['updated_at'] ?? '')];
        }

        $items = $connector->fetchAllFromTable('tbl_items', 'updated_at');
        foreach ($items as $row) {
            $extId = (string) $row['item_code'];
            $events[] = [
                'event_id' => "product:{$extId}:".md5((string) json_encode($row)),
                'entity_type' => 'product',
                'external_id' => $extId,
                'payload' => $row,
            ];
            $cursorUpdates['tbl_items'] = ['cursor_column' => 'updated_at', 'value' => (string) ($row['updated_at'] ?? '')];
        }

        $stockRows = $connector->fetchAllFromTable('tbl_stock_qty', 'last_changed_at');
        foreach ($stockRows as $row) {
            $extId = "{$row['wh_id']}-{$row['item_code']}";
            $events[] = [
                'event_id' => "inv:{$extId}:".md5((string) json_encode($row)),
                'entity_type' => 'inventory_balance',
                'external_id' => $extId,
                'payload' => $row,
            ];
            $cursorUpdates['tbl_stock_qty'] = ['cursor_column' => 'last_changed_at', 'value' => (string) ($row['last_changed_at'] ?? '')];
        }

        $lines = $connector->fetchAllFromTable('tbl_invoice_lines', 'line_id');
        $linesByInv = [];
        foreach ($lines as $line) {
            $linesByInv[(string) $line['inv_id']][] = $line;
            $cursorUpdates['tbl_invoice_lines'] = ['cursor_column' => 'line_id', 'value' => (string) $line['line_id']];
        }

        $invoices = $connector->fetchAllFromTable('tbl_invoices', 'updated_at');
        foreach ($invoices as $inv) {
            $extId = (string) $inv['inv_id'];
            $invLines = $linesByInv[$extId] ?? [];
            $payload = $inv;
            $payload['items'] = $invLines;

            $events[] = [
                'event_id' => "order:{$extId}:".md5((string) json_encode($payload)),
                'entity_type' => 'order',
                'external_id' => $extId,
                'payload' => $payload,
            ];
            $cursorUpdates['tbl_invoices'] = ['cursor_column' => 'updated_at', 'value' => (string) ($inv['updated_at'] ?? '')];
        }

        $run = $this->ingestBatch(
            source: $source,
            instance: null,
            events: $events,
            runType: $runType,
            preloadedMappings: $mappings,
        );

        foreach ($cursorUpdates as $table => $cursorMeta) {
            SyncCursor::query()->updateOrCreate(
                ['data_source_id' => $source->id, 'source_table' => $table],
                [
                    'cursor_column' => $cursorMeta['cursor_column'],
                    'last_cursor_value' => $cursorMeta['value'],
                    'updated_at' => now(),
                ]
            );
        }

        return $run;
    }

    /**
     * @param  list<array{event_id: string, entity_type: string, external_id: string, payload: array<string, mixed>, cursor_table?: string, cursor_column?: string, cursor_value?: string}>  $events
     * @param  Collection<int, FieldMapping>|null  $preloadedMappings
     */
    public function ingestBatch(
        DataSource $source,
        ?ConnectorInstance $instance,
        array $events,
        string $runType = 'incremental',
        ?Collection $preloadedMappings = null,
    ): SyncRun {
        $mappings = $preloadedMappings ?? FieldMapping::query()->where('data_source_id', $source->id)->get();

        $run = SyncRun::query()->create([
            'data_source_id' => $source->id,
            'connector_instance_id' => $instance?->id,
            'run_type' => $runType,
            'status' => 'running',
            'records_received' => count($events),
            'records_upserted' => 0,
            'records_skipped_duplicate' => 0,
            'records_failed' => 0,
            'started_at' => now(),
        ]);

        $upserted = 0;
        $skipped = 0;
        $failed = 0;
        $lastErrorMessage = null;

        foreach ($events as $item) {
            $eventId = (string) $item['event_id'];
            $entityType = (string) $item['entity_type'];
            $externalId = (string) $item['external_id'];
            /** @var array<string, mixed> $rawPayload */
            $rawPayload = is_array($item['payload']) ? $item['payload'] : [];

            $alreadyProcessed = SyncEvent::query()
                ->where('data_source_id', $source->id)
                ->where('event_id', $eventId)
                ->whereIn('status', ['processed', 'duplicate'])
                ->exists();

            if ($alreadyProcessed) {
                $skipped++;

                continue;
            }

            $syncEvent = SyncEvent::query()->updateOrCreate(
                ['data_source_id' => $source->id, 'event_id' => $eventId],
                [
                    'sync_run_id' => $run->id,
                    'entity_type' => $entityType,
                    'external_id' => $externalId,
                    'payload_json' => $rawPayload,
                    'status' => 'received',
                ]
            );

            try {
                DB::transaction(function () use ($source, $entityType, $externalId, $rawPayload, $mappings, $eventId, $syncEvent, $item): void {
                    $this->applyCanonicalEntity($source, $entityType, $externalId, $rawPayload, $mappings, $eventId);

                    $syncEvent->forceFill([
                        'status' => 'processed',
                        'error_message' => null,
                        'processed_at' => now(),
                    ])->save();

                    if (! empty($item['cursor_table']) && ! empty($item['cursor_column']) && isset($item['cursor_value'])) {
                        SyncCursor::query()->updateOrCreate(
                            ['data_source_id' => $source->id, 'source_table' => (string) $item['cursor_table']],
                            [
                                'cursor_column' => (string) $item['cursor_column'],
                                'last_cursor_value' => (string) $item['cursor_value'],
                                'updated_at' => now(),
                            ]
                        );
                    }
                });

                $upserted++;
            } catch (Throwable $e) {
                $failed++;
                $lastErrorMessage = $e->getMessage();

                $syncEvent->forceFill([
                    'status' => 'failed',
                    'error_message' => $lastErrorMessage,
                ])->save();

                SyncError::query()->create([
                    'data_source_id' => $source->id,
                    'sync_run_id' => $run->id,
                    'entity_type' => $entityType,
                    'external_id' => $externalId,
                    'error_code' => 'INGEST_VALIDATION_OR_MAPPING_ERROR',
                    'error_message' => $lastErrorMessage,
                    'payload_excerpt_json' => array_slice($rawPayload, 0, 12, true),
                ]);

                Log::channel('sync')->warning('Sync event failed during normalization/upsert', [
                    'tenant_id' => $source->tenant_id,
                    'data_source_id' => $source->id,
                    'event_id' => $eventId,
                    'entity_type' => $entityType,
                    'error' => $lastErrorMessage,
                ]);
            }
        }

        $runStatus = match (true) {
            $failed > 0 && $upserted === 0 && $skipped === 0 => 'failed',
            $failed > 0 => 'partial_failed',
            default => 'succeeded',
        };

        $run->forceFill([
            'status' => $runStatus,
            'records_upserted' => $upserted,
            'records_skipped_duplicate' => $skipped,
            'records_failed' => $failed,
            'finished_at' => now(),
            'error_summary' => $lastErrorMessage,
        ])->save();

        $now = now();
        $sourceUpdates = ['last_heartbeat_at' => $now];
        if ($runStatus === 'failed') {
            $sourceUpdates['status'] = 'error';
            $sourceUpdates['last_error_summary'] = $lastErrorMessage;
        } else {
            $sourceUpdates['status'] = 'active';
            $sourceUpdates['last_successful_sync_at'] = $now;
            $sourceUpdates['last_error_summary'] = $failed > 0 ? $lastErrorMessage : null;
        }
        $source->forceFill($sourceUpdates)->save();

        $newFreshness = $this->freshnessEvaluator->evaluate($source->fresh(), $now);
        $source->forceFill(['freshness_status' => $newFreshness])->save();

        event(new SyncBatchCompleted(
            tenantId: (int) $source->tenant_id,
            dataSourceId: (int) $source->id,
            syncRunId: (int) $run->id,
            status: $runStatus,
            recordsUpserted: $upserted,
            freshnessStatus: $newFreshness,
        ));

        event(new DataFreshnessUpdated(
            tenantId: (int) $source->tenant_id,
            dataSourceId: (int) $source->id,
            freshnessStatus: $newFreshness,
            lastHeartbeatAtIso: $source->last_heartbeat_at?->utc()->toIso8601String(),
            lastSuccessfulSyncAtIso: $source->last_successful_sync_at?->utc()->toIso8601String(),
        ));

        return $run->fresh();
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  Collection<int, FieldMapping>  $mappings
     */
    private function applyCanonicalEntity(
        DataSource $source,
        string $entityType,
        string $externalId,
        array $rawPayload,
        Collection $mappings,
        string $eventId,
    ): void {
        match ($entityType) {
            'branch' => $this->upsertBranch($source, $externalId, $rawPayload, $mappings),
            'warehouse' => $this->upsertWarehouse($source, $externalId, $rawPayload, $mappings),
            'customer' => $this->upsertCustomer($source, $externalId, $rawPayload, $mappings),
            'product' => $this->upsertProduct($source, $externalId, $rawPayload, $mappings),
            'inventory_balance' => $this->upsertInventoryBalance($source, $externalId, $rawPayload, $mappings, $eventId),
            'order' => $this->upsertOrder($source, $externalId, $rawPayload, $mappings),
            default => throw new DomainException("Unsupported canonical entity type [{$entityType}]."),
        };
    }

    private function upsertBranch(DataSource $source, string $externalId, array $raw, Collection $mappings): void
    {
        $data = $this->normalizer->normalize('branch', $raw, $mappings);
        $code = (string) ($data['code'] ?? 'SRC-BR-'.$externalId);

        $mappedId = $this->findInternalId($source, 'branch', $externalId);
        $branch = $mappedId !== null
            ? Branch::query()->find($mappedId)
            : Branch::query()->where('code', $code)->first();

        if ($branch === null) {
            $branch = Branch::query()->create([
                'code' => $code,
                'name' => (string) $data['name'],
                'city' => (string) ($data['city'] ?? 'Riyadh'),
                'status' => 'active',
                'timezone' => 'Asia/Riyadh',
            ]);
        } else {
            $branch->update([
                'name' => (string) $data['name'],
                'city' => (string) ($data['city'] ?? $branch->city),
            ]);
        }

        $this->recordExternalMapping($source, 'branch', $externalId, $branch->id, $raw);
    }

    private function upsertWarehouse(DataSource $source, string $externalId, array $raw, Collection $mappings): void
    {
        $data = $this->normalizer->normalize('warehouse', $raw, $mappings);
        $branchExtId = (string) $data['branch_external_id'];
        $branchId = $this->findInternalId($source, 'branch', $branchExtId);

        if ($branchId === null) {
            throw new DomainException("Referenced external branch [{$branchExtId}] has not been synced yet.");
        }

        $code = (string) ($data['code'] ?? 'SRC-WH-'.$externalId);
        $mappedId = $this->findInternalId($source, 'warehouse', $externalId);
        $warehouse = $mappedId !== null
            ? Warehouse::query()->find($mappedId)
            : Warehouse::query()->where('code', $code)->first();

        if ($warehouse === null) {
            $warehouse = Warehouse::query()->create([
                'branch_id' => $branchId,
                'code' => $code,
                'name' => (string) $data['name'],
                'is_default' => false,
                'status' => 'active',
            ]);
        } else {
            $warehouse->update([
                'branch_id' => $branchId,
                'name' => (string) $data['name'],
            ]);
        }

        $this->recordExternalMapping($source, 'warehouse', $externalId, $warehouse->id, $raw);
    }

    private function upsertCustomer(DataSource $source, string $externalId, array $raw, Collection $mappings): void
    {
        $data = $this->normalizer->normalize('customer', $raw, $mappings);
        $code = (string) ($data['code'] ?? 'SRC-CUST-'.$externalId);

        $mappedId = $this->findInternalId($source, 'customer', $externalId);
        $customer = $mappedId !== null
            ? Customer::query()->find($mappedId)
            : Customer::query()->where('code', $code)->first();

        $attrs = [
            'code' => $code,
            'name' => (string) $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => 'active',
            'source_updated_at' => ! empty($data['source_updated_at']) ? CarbonImmutable::parse((string) $data['source_updated_at']) : null,
        ];

        if ($customer === null) {
            $customer = Customer::query()->create($attrs);
        } else {
            $customer->update($attrs);
        }

        $this->recordExternalMapping($source, 'customer', $externalId, $customer->id, $raw);
    }

    private function upsertProduct(DataSource $source, string $externalId, array $raw, Collection $mappings): void
    {
        $data = $this->normalizer->normalize('product', $raw, $mappings);
        $sku = (string) $data['sku'];

        $categoryId = null;
        if (! empty($data['category_name'])) {
            $catName = (string) $data['category_name'];
            $catCode = 'SRC-CAT-'.substr(md5($catName), 0, 8);
            $category = Category::query()->updateOrCreate(
                ['code' => $catCode],
                ['name' => $catName, 'status' => 'active']
            );
            $categoryId = $category->id;
        }

        $mappedId = $this->findInternalId($source, 'product', $externalId);
        $product = $mappedId !== null
            ? Product::query()->find($mappedId)
            : Product::query()->where('sku', $sku)->first();

        $statusVal = (string) ($data['status'] ?? 'active');
        $attrs = [
            'category_id' => $categoryId,
            'sku' => $sku,
            'name' => (string) $data['name'],
            'status' => in_array($statusVal, ['active', 'inactive'], true) ? $statusVal : 'active',
            'base_price' => (string) $data['base_price'],
            'cost_price' => $data['cost_price'] ?? null,
            'currency_code' => 'SAR',
            'reorder_point' => '5.0000',
            'source_updated_at' => ! empty($data['source_updated_at']) ? CarbonImmutable::parse((string) $data['source_updated_at']) : null,
        ];

        if ($product === null) {
            $product = Product::query()->create($attrs);
        } else {
            $product->update($attrs);
        }

        $this->recordExternalMapping($source, 'product', $externalId, $product->id, $raw);
    }

    private function upsertInventoryBalance(
        DataSource $source,
        string $externalId,
        array $raw,
        Collection $mappings,
        string $eventId,
    ): void {
        $data = $this->normalizer->normalize('inventory_balance', $raw, $mappings);
        $whExtId = (string) $data['warehouse_external_id'];
        $prodExtId = (string) $data['product_external_id'];

        $warehouseId = $this->findInternalId($source, 'warehouse', $whExtId);
        $productId = $this->findInternalId($source, 'product', $prodExtId);

        if ($warehouseId === null || $productId === null) {
            throw new DomainException("Cannot sync balance [{$externalId}]: missing warehouse [{$whExtId}] or product [{$prodExtId}] mapping.");
        }

        $balance = $this->inventoryLedger->reconcileSyncedBalance(
            warehouseId: $warehouseId,
            productId: $productId,
            targetOnHand: (string) $data['on_hand'],
            targetReserved: (string) ($data['reserved'] ?? '0.0000'),
            idempotencyKey: "sync:{$source->id}:{$eventId}",
            notes: "Synced from {$source->name}",
        );

        $this->recordExternalMapping($source, 'inventory_balance', $externalId, $balance->id, $raw);
    }

    private function upsertOrder(DataSource $source, string $externalId, array $raw, Collection $mappings): void
    {
        $data = $this->normalizer->normalize('order', $raw, $mappings);
        $branchExtId = (string) $data['branch_external_id'];
        $branchId = $this->findInternalId($source, 'branch', $branchExtId);

        if ($branchId === null) {
            throw new DomainException("Cannot sync order [{$externalId}]: missing branch [{$branchExtId}] mapping.");
        }

        $warehouseId = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        $customerId = ! empty($data['customer_external_id'])
            ? $this->findInternalId($source, 'customer', (string) $data['customer_external_id'])
            : null;

        $mappedOrderId = $this->findInternalId($source, 'order', $externalId);
        $orderNumber = (string) $data['order_number'];

        $existingOrder = $mappedOrderId !== null
            ? Order::query()->find($mappedOrderId)
            : Order::query()->where('order_number', $orderNumber)->first();

        $rawStatus = (string) ($data['status'] ?? 'completed');
        [$orderStatus, $paymentStatus, $fulfillmentStatus] = match ($rawStatus) {
            'partial' => ['confirmed', 'partial', 'fulfilled'],
            'unpaid' => ['confirmed', 'unpaid', 'unfulfilled'],
            'refunded' => ['completed', 'refunded', 'returned'],
            'cancelled' => ['cancelled', 'unpaid', 'unfulfilled'],
            default => ['completed', 'paid', 'fulfilled'],
        };

        $gross = (string) $data['gross_sales'];
        $discount = (string) ($data['discount_total'] ?? '0.0000');
        $tax = (string) ($data['tax_total'] ?? '0.0000');
        $netSales = DecimalMath::sub($gross, $discount);
        $payable = DecimalMath::add($netSales, $tax);
        $paid = (string) ($data['paid_total'] ?? '0.0000');
        $refunded = $paymentStatus === 'refunded' ? $payable : DecimalMath::zero();
        $outstanding = $paymentStatus === 'refunded'
            ? DecimalMath::zero()
            : DecimalMath::maxZero(DecimalMath::sub($payable, $paid));

        $rawItems = is_array($raw['items'] ?? null) ? $raw['items'] : [];
        $cogsTotal = DecimalMath::zero();
        $hasCompleteCostData = count($rawItems) > 0;
        $preparedItems = [];

        foreach ($rawItems as $rawItem) {
            $itemData = $this->normalizer->normalize('order_item', (array) $rawItem, $mappings);
            $prodExtId = (string) $itemData['product_external_id'];
            $productId = $this->findInternalId($source, 'product', $prodExtId);

            if ($productId === null) {
                throw new DomainException("Cannot sync order [{$orderNumber}]: missing product [{$prodExtId}] mapping.");
            }

            /** @var Product $product */
            $product = Product::query()->findOrFail($productId);
            $qty = (string) $itemData['quantity'];
            $unitPrice = (string) $itemData['unit_price'];
            $lineTotal = (string) ($itemData['line_total'] ?? DecimalMath::mul($qty, $unitPrice));
            $unitCost = $product->cost_price !== null ? DecimalMath::normalize((string) $product->cost_price) : null;

            if ($unitCost === null) {
                $hasCompleteCostData = false;
            } else {
                $cogsTotal = DecimalMath::add($cogsTotal, DecimalMath::mul($qty, $unitCost));
            }

            $preparedItems[] = [
                'product_id' => $product->id,
                'sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'quantity' => $qty,
                'unit_price_snapshot' => $unitPrice,
                'unit_cost_snapshot' => $unitCost,
                'discount_amount' => DecimalMath::zero(),
                'tax_amount' => DecimalMath::zero(),
                'line_total' => $lineTotal,
                'external_item_id' => isset($itemData['external_id']) ? (string) $itemData['external_id'] : null,
            ];
        }

        $orderAttrs = [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'currency_code' => strtoupper((string) ($data['currency_code'] ?? 'SAR')),
            'gross_sales' => $gross,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'net_sales' => $netSales,
            'paid_total' => $paid,
            'refunded_total' => $refunded,
            'outstanding_total' => $outstanding,
            'cogs_total' => $hasCompleteCostData ? $cogsTotal : null,
            'has_complete_cost_data' => $hasCompleteCostData,
            'placed_at' => CarbonImmutable::parse((string) $data['placed_at']),
            'source_updated_at' => ! empty($data['source_updated_at']) ? CarbonImmutable::parse((string) $data['source_updated_at']) : null,
        ];

        if ($existingOrder === null) {
            $order = Order::query()->create($orderAttrs);
            foreach ($preparedItems as $pi) {
                $extItemId = $pi['external_item_id'];
                unset($pi['external_item_id']);
                $createdItem = OrderItem::query()->create(array_merge($pi, ['order_id' => $order->id]));

                if ($extItemId !== null) {
                    $this->recordExternalMapping($source, 'order_item', $extItemId, $createdItem->id, $pi);
                }
            }
        } else {
            $existingOrder->update($orderAttrs);
            $order = $existingOrder;
        }

        if (DecimalMath::gt($paid, '0')) {
            Payment::query()->firstOrCreate(
                ['idempotency_key' => "sync:{$source->id}:order:{$externalId}:pay"],
                [
                    'order_id' => $order->id,
                    'payment_reference' => "SYNC-{$order->order_number}",
                    'method' => 'pos_synced',
                    'amount' => $paid,
                    'currency_code' => $order->currency_code,
                    'status' => 'captured',
                    'paid_at' => $order->placed_at,
                ]
            );
        }

        if (DecimalMath::gt($refunded, '0')) {
            Refund::query()->firstOrCreate(
                ['idempotency_key' => "sync:{$source->id}:order:{$externalId}:refund"],
                [
                    'order_id' => $order->id,
                    'amount' => $refunded,
                    'reason' => 'Synced refunded invoice from POS',
                    'restock_inventory' => false,
                    'refunded_at' => $order->source_updated_at ?? $order->placed_at,
                ]
            );
        }

        $this->recordExternalMapping($source, 'order', $externalId, $order->id, $raw);
    }

    private function findInternalId(DataSource $source, string $entity, string $externalId): ?int
    {
        $val = ExternalMapping::query()
            ->where('data_source_id', $source->id)
            ->where('canonical_entity', $entity)
            ->where('external_id', $externalId)
            ->value('internal_id');

        return $val !== null ? (int) $val : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function recordExternalMapping(
        DataSource $source,
        string $entity,
        string $externalId,
        int $internalId,
        array $raw,
    ): void {
        ExternalMapping::query()->updateOrCreate(
            [
                'data_source_id' => $source->id,
                'canonical_entity' => $entity,
                'external_id' => $externalId,
            ],
            [
                'internal_id' => $internalId,
                'source_hash' => hash('sha256', (string) json_encode($raw)),
                'last_synced_at' => now(),
            ]
        );
    }
}
