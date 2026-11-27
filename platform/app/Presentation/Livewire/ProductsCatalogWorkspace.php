<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Sales\Models\Category;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

final class ProductsCatalogWorkspace extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = 'all';

    public string $statusFilter = 'all';

    public bool $showProductDrawer = false;

    public ?int $selectedProductId = null;

    public function mount(TenantContext $tenantContext): void
    {
        if (! $tenantContext->hasAnyPermission(['inventory.view', 'sales.view'])) {
            abort(403, 'Missing permission to view products catalog.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openProduct(int $productId): void
    {
        Product::query()->findOrFail($productId);
        $this->selectedProductId = $productId;
        $this->showProductDrawer = true;
    }

    #[On('sync-realtime-refresh')]
    public function refreshFromRealtime(): void
    {
        // Auto-refresh on Reverb sync broadcast
    }

    public function render(TenantContext $tenantContext): View
    {
        if (! $tenantContext->hasAnyPermission(['inventory.view', 'sales.view'])) {
            abort(403, 'Missing permission to view products catalog.');
        }

        $canViewCost = $tenantContext->canViewCost();
        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        $warehousesQuery = Warehouse::query()->orderBy('id');
        if ($activeBranchId !== null) {
            $warehousesQuery->where('branch_id', $activeBranchId);
        } elseif (is_array($accessibleBranchIds)) {
            $warehousesQuery->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        $allowedWarehouseIds = $warehousesQuery->pluck('id')->all();

        $query = Product::query()->with('category:id,name,code');

        if ($this->categoryFilter !== 'all') {
            $query->where('category_id', (int) $this->categoryFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('sku', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        $products = $query->orderBy('id')->paginate(12);
        $productIds = $products->pluck('id')->all();

        // Aggregate On Hand / Reserved / Available ONLY across permitted warehouses
        $stockTotals = [];
        if ($productIds !== [] && $allowedWarehouseIds !== []) {
            $balances = InventoryBalance::query()
                ->whereIn('product_id', $productIds)
                ->whereIn('warehouse_id', $allowedWarehouseIds)
                ->get();

            foreach ($balances as $bal) {
                if (! isset($stockTotals[$bal->product_id])) {
                    $stockTotals[$bal->product_id] = [
                        'on_hand' => DecimalMath::zero(),
                        'reserved' => DecimalMath::zero(),
                        'available' => DecimalMath::zero(),
                    ];
                }
                $stockTotals[$bal->product_id]['on_hand'] = DecimalMath::add($stockTotals[$bal->product_id]['on_hand'], (string) $bal->on_hand);
                $stockTotals[$bal->product_id]['reserved'] = DecimalMath::add($stockTotals[$bal->product_id]['reserved'], (string) $bal->reserved);
                $stockTotals[$bal->product_id]['available'] = DecimalMath::add($stockTotals[$bal->product_id]['available'], (string) $bal->available);
            }
        }

        $externalRefs = ExternalMapping::query()
            ->where('canonical_entity', 'product')
            ->whereIn('internal_id', $productIds ?: [-1])
            ->pluck('external_id', 'internal_id')
            ->all();

        $selectedProduct = null;
        $selectedWarehouseBalances = collect();
        $selectedExternalRef = null;
        if ($this->showProductDrawer && $this->selectedProductId !== null) {
            $selectedProduct = Product::query()
                ->with(['category', 'variants'])
                ->find($this->selectedProductId);

            if ($selectedProduct !== null) {
                $selectedWarehouseBalances = InventoryBalance::query()
                    ->with('warehouse.branch')
                    ->where('product_id', $selectedProduct->id)
                    ->whereIn('warehouse_id', $allowedWarehouseIds === [] ? [-1] : $allowedWarehouseIds)
                    ->get();

                $selectedExternalRef = ExternalMapping::query()
                    ->where('canonical_entity', 'product')
                    ->where('internal_id', $selectedProduct->id)
                    ->value('external_id');
            }
        }

        return view('livewire.products-catalog-workspace', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'stockTotals' => $stockTotals,
            'externalRefs' => $externalRefs,
            'canViewCost' => $canViewCost,
            'selectedProduct' => $selectedProduct,
            'selectedWarehouseBalances' => $selectedWarehouseBalances,
            'selectedExternalRef' => $selectedExternalRef,
            'decimalHelper' => DecimalMath::class,
        ]);
    }
}
