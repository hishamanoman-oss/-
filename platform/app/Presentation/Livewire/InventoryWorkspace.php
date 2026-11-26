<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Services\TenantContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

final class InventoryWorkspace extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = 'all';

    // Movements Drawer
    public bool $showMovementsDrawer = false;

    public ?int $selectedWarehouseId = null;

    public ?int $selectedProductId = null;

    public string $selectedLabel = '';

    // Stock Adjustment Modal
    public bool $showAdjustModal = false;

    public ?int $adjustWarehouseId = null;

    public ?int $adjustProductId = null;

    public string $adjustDelta = '';

    public string $adjustNotes = '';

    // Warehouse Transfer Modal
    public bool $showTransferModal = false;

    public ?int $transferFromWarehouseId = null;

    public ?int $transferToWarehouseId = null;

    public ?int $transferProductId = null;

    public string $transferQuantity = '';

    public string $transferNotes = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', InventoryBalance::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function openMovements(int $balanceId): void
    {
        $balance = InventoryBalance::query()
            ->with(['warehouse.branch', 'product'])
            ->findOrFail($balanceId);

        Gate::authorize('viewBalance', $balance);

        $this->selectedWarehouseId = $balance->warehouse_id;
        $this->selectedProductId = $balance->product_id;
        $this->selectedLabel = "{$balance->product->name} ({$balance->product->sku}) — {$balance->warehouse->name}";
        $this->showMovementsDrawer = true;
    }

    public function openAdjustModal(?int $warehouseId = null, ?int $productId = null): void
    {
        $this->resetValidation();
        $this->adjustWarehouseId = $warehouseId;
        $this->adjustProductId = $productId;
        $this->adjustDelta = '';
        $this->adjustNotes = '';
        $this->showAdjustModal = true;
    }

    public function saveAdjustment(InventoryLedgerService $ledger): void
    {
        $validated = $this->validate([
            'adjustWarehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'adjustProductId' => ['required', 'integer', 'exists:products,id'],
            'adjustDelta' => ['required', 'numeric', 'not_in:0'],
            'adjustNotes' => ['required', 'string', 'max:255'],
        ]);

        $warehouse = Warehouse::query()->findOrFail((int) $validated['adjustWarehouseId']);
        Gate::authorize('adjust', $warehouse);

        try {
            $ledger->adjustStock(
                warehouseId: $warehouse->id,
                productId: (int) $validated['adjustProductId'],
                quantityDelta: (string) $validated['adjustDelta'],
                idempotencyKey: 'ui-adj-'.Str::uuid()->toString(),
                notes: trim($validated['adjustNotes']),
            );
        } catch (DomainException $e) {
            $this->addError('adjustDelta', $e->getMessage());

            return;
        }

        $this->showAdjustModal = false;
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function openTransferModal(): void
    {
        $this->resetValidation();
        $this->transferFromWarehouseId = null;
        $this->transferToWarehouseId = null;
        $this->transferProductId = null;
        $this->transferQuantity = '';
        $this->transferNotes = '';
        $this->showTransferModal = true;
    }

    public function saveTransfer(InventoryLedgerService $ledger): void
    {
        $validated = $this->validate([
            'transferFromWarehouseId' => ['required', 'integer', 'exists:warehouses,id'],
            'transferToWarehouseId' => ['required', 'integer', 'exists:warehouses,id', 'different:transferFromWarehouseId'],
            'transferProductId' => ['required', 'integer', 'exists:products,id'],
            'transferQuantity' => ['required', 'numeric', 'gt:0'],
            'transferNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $fromWarehouse = Warehouse::query()->findOrFail((int) $validated['transferFromWarehouseId']);
        $toWarehouse = Warehouse::query()->findOrFail((int) $validated['transferToWarehouseId']);
        Gate::authorize('adjust', $fromWarehouse);
        Gate::authorize('adjust', $toWarehouse);

        try {
            $ledger->transferBetweenWarehouses(
                fromWarehouseId: $fromWarehouse->id,
                toWarehouseId: $toWarehouse->id,
                productId: (int) $validated['transferProductId'],
                quantity: (string) $validated['transferQuantity'],
                idempotencyKey: 'ui-trf-'.Str::uuid()->toString(),
                notes: $validated['transferNotes'] ? trim($validated['transferNotes']) : null,
            );
        } catch (DomainException $e) {
            $this->addError('transferQuantity', $e->getMessage());

            return;
        }

        $this->showTransferModal = false;
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', InventoryBalance::class);

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();

        $warehousesQuery = Warehouse::query()->with('branch')->orderBy('name');
        if (is_array($accessibleBranchIds)) {
            $warehousesQuery->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        if ($tenantContext->activeBranchId() !== null) {
            $warehousesQuery->where('branch_id', $tenantContext->activeBranchId());
        }
        $warehouses = $warehousesQuery->get();
        $allowedWarehouseIds = $warehouses->pluck('id')->all();

        $balancesQuery = InventoryBalance::query()
            ->with(['warehouse.branch', 'product', 'variant'])
            ->whereIn('warehouse_id', $allowedWarehouseIds === [] ? [-1] : $allowedWarehouseIds);

        if ($this->warehouseFilter !== 'all') {
            $balancesQuery->where('warehouse_id', (int) $this->warehouseFilter);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $balancesQuery->whereHas('product', function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            });
        }

        $movements = collect();
        if ($this->showMovementsDrawer && $this->selectedWarehouseId !== null && $this->selectedProductId !== null) {
            $movements = StockMovement::query()
                ->with('actor:id,name')
                ->where('warehouse_id', $this->selectedWarehouseId)
                ->where('product_id', $this->selectedProductId)
                ->latest('id')
                ->limit(25)
                ->get();
        }

        return view('livewire.inventory-workspace', [
            'balances' => $balancesQuery->orderBy('warehouse_id')->orderBy('product_id')->paginate(12),
            'warehouses' => $warehouses,
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(),
            'movements' => $movements,
            'canAdjust' => $tenantContext->hasPermission('inventory.adjust'),
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
