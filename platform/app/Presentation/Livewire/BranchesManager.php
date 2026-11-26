<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Application\Services\AuditLogger;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class BranchesManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $sortField = 'id';

    public string $sortDirection = 'asc';

    // Branch modal state
    public bool $showBranchModal = false;

    public ?int $editingBranchId = null;

    public string $branchCode = '';

    public string $branchName = '';

    public string $branchCity = '';

    public string $branchStatus = 'active';

    public string $branchTimezone = 'Asia/Riyadh';

    // Warehouse modal state
    public bool $showWarehouseModal = false;

    public ?int $editingWarehouseId = null;

    public ?int $warehouseBranchId = null;

    public string $warehouseCode = '';

    public string $warehouseName = '';

    public bool $warehouseIsDefault = false;

    public string $warehouseStatus = 'active';

    public function mount(): void
    {
        Gate::authorize('viewAny', Branch::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['id', 'code', 'name', 'city', 'status'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateBranch(): void
    {
        Gate::authorize('create', Branch::class);

        $this->resetValidation();
        $this->editingBranchId = null;
        $this->branchCode = '';
        $this->branchName = '';
        $this->branchCity = '';
        $this->branchStatus = 'active';
        $this->branchTimezone = 'Asia/Riyadh';
        $this->showBranchModal = true;
    }

    public function openEditBranch(int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        Gate::authorize('update', $branch);

        $this->resetValidation();
        $this->editingBranchId = $branch->id;
        $this->branchCode = $branch->code;
        $this->branchName = $branch->name;
        $this->branchCity = $branch->city;
        $this->branchStatus = $branch->status;
        $this->branchTimezone = (string) ($branch->timezone ?? 'Asia/Riyadh');
        $this->showBranchModal = true;
    }

    public function saveBranch(TenantContext $tenantContext, AuditLogger $audit): void
    {
        $tenantId = $tenantContext->id();

        if ($this->editingBranchId !== null) {
            $branch = Branch::query()->findOrFail($this->editingBranchId);
            Gate::authorize('update', $branch);
        } else {
            Gate::authorize('create', Branch::class);
            $branch = null;
        }

        $validated = $this->validate([
            'branchCode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('branches', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($this->editingBranchId),
            ],
            'branchName' => ['required', 'string', 'max:150'],
            'branchCity' => ['required', 'string', 'max:80'],
            'branchStatus' => ['required', 'in:active,inactive'],
            'branchTimezone' => ['nullable', 'string', 'max:64'],
        ]);

        if ($branch !== null) {
            $branch->update([
                'code' => strtoupper(trim($validated['branchCode'])),
                'name' => trim($validated['branchName']),
                'city' => trim($validated['branchCity']),
                'status' => $validated['branchStatus'],
                'timezone' => $validated['branchTimezone'] ?: 'Asia/Riyadh',
            ]);

            $audit->log('branch.updated', 'branch', $branch->id, ['code' => $branch->code, 'name' => $branch->name]);
            $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
        } else {
            $created = Branch::query()->create([
                'code' => strtoupper(trim($validated['branchCode'])),
                'name' => trim($validated['branchName']),
                'city' => trim($validated['branchCity']),
                'status' => $validated['branchStatus'],
                'timezone' => $validated['branchTimezone'] ?: 'Asia/Riyadh',
            ]);

            $audit->log('branch.created', 'branch', $created->id, ['code' => $created->code, 'name' => $created->name]);
            $this->dispatch('toast', message: __('ui.created_successfully'), type: 'success');
        }

        $this->showBranchModal = false;
    }

    public function toggleBranchStatus(int $branchId, AuditLogger $audit): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        Gate::authorize('toggleStatus', $branch);

        $newStatus = $branch->status === 'active' ? 'inactive' : 'active';
        $branch->update(['status' => $newStatus]);

        $audit->log('branch.status_toggled', 'branch', $branch->id, ['status' => $newStatus]);
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function openCreateWarehouse(?int $branchId = null): void
    {
        $this->resetValidation();
        $this->editingWarehouseId = null;
        $this->warehouseBranchId = $branchId;
        $this->warehouseCode = '';
        $this->warehouseName = '';
        $this->warehouseIsDefault = false;
        $this->warehouseStatus = 'active';
        $this->showWarehouseModal = true;
    }

    public function saveWarehouse(TenantContext $tenantContext, AuditLogger $audit): void
    {
        $tenantId = $tenantContext->id();

        $validated = $this->validate([
            'warehouseBranchId' => ['required', 'integer', 'exists:branches,id'],
            'warehouseCode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('warehouses', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($this->editingWarehouseId),
            ],
            'warehouseName' => ['required', 'string', 'max:150'],
            'warehouseIsDefault' => ['boolean'],
            'warehouseStatus' => ['required', 'in:active,inactive'],
        ]);

        $branch = Branch::query()->findOrFail((int) $validated['warehouseBranchId']);
        Gate::authorize('createForBranch', [Warehouse::class, $branch]);

        if ($validated['warehouseIsDefault']) {
            Warehouse::query()->where('branch_id', $branch->id)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'code' => strtoupper(trim($validated['warehouseCode'])),
            'name' => trim($validated['warehouseName']),
            'is_default' => (bool) $validated['warehouseIsDefault'],
            'status' => $validated['warehouseStatus'],
        ]);

        $audit->log('warehouse.created', 'warehouse', $warehouse->id, [
            'branch_id' => $branch->id,
            'code' => $warehouse->code,
        ]);

        $this->showWarehouseModal = false;
        $this->dispatch('toast', message: __('ui.created_successfully'), type: 'success');
    }

    public function toggleWarehouseStatus(int $warehouseId, AuditLogger $audit): void
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        Gate::authorize('update', $warehouse);

        $newStatus = $warehouse->status === 'active' ? 'inactive' : 'active';
        $warehouse->update(['status' => $newStatus]);

        $audit->log('warehouse.status_toggled', 'warehouse', $warehouse->id, ['status' => $newStatus]);
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', Branch::class);

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();

        $branchesQuery = Branch::query()->with(['warehouses' => fn ($q) => $q->orderBy('id')]);

        if (is_array($accessibleBranchIds)) {
            $branchesQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }

        if ($tenantContext->activeBranchId() !== null) {
            $branchesQuery->where('id', $tenantContext->activeBranchId());
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $branchesQuery->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        if ($this->statusFilter !== 'all') {
            $branchesQuery->where('status', $this->statusFilter);
        }

        $branches = $branchesQuery
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        $availableBranchesQuery = Branch::query()->orderBy('name');
        if (is_array($accessibleBranchIds)) {
            $availableBranchesQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }

        return view('livewire.branches-manager', [
            'branches' => $branches,
            'availableBranches' => $availableBranchesQuery->get(),
            'canCreateBranch' => Gate::allows('create', Branch::class),
            'canManageWarehouses' => $tenantContext->hasPermission('warehouses.manage'),
        ]);
    }
}
