<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Commands\Services\RemoteCommandService;
use App\Domain\Finance\Support\DecimalMath;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\ExternalMapping;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Orders\Models\Order;
use App\Domain\Sales\Models\Product;
use App\Domain\Tenants\Services\TenantContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

final class CommandCenterWorkspace extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';

    // New Command Drawer
    public bool $showCreateDrawer = false;

    public ?int $dataSourceId = null;

    public string $commandType = 'product.price.update';

    public ?int $targetInternalId = null;

    public string $newPrice = '';

    public string $newOrderStatus = 'completed';

    public string $stockDelta = '';

    public string $reason = '';

    // Detail Drawer
    public bool $showDetailDrawer = false;

    public ?int $selectedCommandId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', RemoteCommand::class);

        $firstWriteSource = DataSource::query()->where('status', 'active')->orderBy('id')->first();
        $this->dataSourceId = $firstWriteSource?->id;
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateDrawer(TenantContext $tenantContext): void
    {
        Gate::authorize('create', RemoteCommand::class);

        $this->resetValidation();
        $this->commandType = 'product.price.update';
        $this->newPrice = '';
        $this->stockDelta = '';
        $this->reason = '';

        if ($this->dataSourceId === null) {
            $this->dataSourceId = DataSource::query()->where('status', 'active')->orderBy('id')->value('id');
        }

        $this->selectDefaultTarget($tenantContext);
        $this->showCreateDrawer = true;
    }

    public function updatedCommandType(TenantContext $tenantContext): void
    {
        $this->resetValidation();
        $this->targetInternalId = null;
        $this->selectDefaultTarget($tenantContext);
    }

    public function submitCommand(RemoteCommandService $commandService): void
    {
        Gate::authorize('create', RemoteCommand::class);

        $validated = $this->validate([
            'dataSourceId' => ['required', 'integer'],
            'commandType' => ['required', 'in:product.price.update,order.status.update,inventory.stock.adjust'],
            'targetInternalId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:250'],
            'newPrice' => ['required_if:commandType,product.price.update', 'nullable', 'numeric', 'gt:0'],
            'newOrderStatus' => ['required_if:commandType,order.status.update', 'nullable', 'in:completed,partial,unpaid,refunded,cancelled'],
            'stockDelta' => ['required_if:commandType,inventory.stock.adjust', 'nullable', 'numeric'],
        ]);

        $source = DataSource::query()->findOrFail((int) $validated['dataSourceId']);

        $params = match ($validated['commandType']) {
            'product.price.update' => [
                'new_unit_price' => (string) $validated['newPrice'],
                'reason' => trim((string) $validated['reason']),
            ],
            'order.status.update' => [
                'new_inv_status' => (string) $validated['newOrderStatus'],
                'reason' => trim((string) $validated['reason']),
            ],
            default => [
                'delta' => (string) $validated['stockDelta'],
                'reason' => trim((string) $validated['reason']),
            ],
        };

        try {
            $cmd = $commandService->requestCommand(
                actor: Auth::user(),
                source: $source,
                commandType: $validated['commandType'],
                targetInternalId: (int) $validated['targetInternalId'],
                params: $params,
            );

            $this->showCreateDrawer = false;
            $this->selectedCommandId = $cmd->id;
            $this->dispatch('toast', message: __('ui.command_submitted_toast', ['id' => $cmd->id]), type: 'success');
        } catch (DomainException $e) {
            $this->addError('commandType', $e->getMessage());
        }
    }

    public function approveCommand(int $commandId, RemoteCommandService $commandService): void
    {
        $command = RemoteCommand::query()->findOrFail($commandId);
        Gate::authorize('approve', $command);

        try {
            $commandService->approve($command, Auth::user(), 'Approved via Command Center UI');
            $this->dispatch('toast', message: __('ui.command_approved_toast', ['id' => $commandId]), type: 'success');
        } catch (DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function rejectCommand(int $commandId, RemoteCommandService $commandService): void
    {
        $command = RemoteCommand::query()->findOrFail($commandId);
        Gate::authorize('approve', $command);

        try {
            $commandService->reject($command, Auth::user(), 'Rejected via Command Center UI');
            $this->dispatch('toast', message: __('ui.command_rejected_toast', ['id' => $commandId]), type: 'success');
        } catch (DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openDetail(int $commandId): void
    {
        $command = RemoteCommand::query()->findOrFail($commandId);
        Gate::authorize('view', $command);

        $this->selectedCommandId = $command->id;
        $this->showDetailDrawer = true;
    }

    #[On('command-realtime-refresh')]
    #[On('sync-realtime-refresh')]
    public function refreshFromRealtime(): void
    {
        // Auto-refresh outbox table & open detail drawer when Reverb receives command.updated
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', RemoteCommand::class);

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        $query = RemoteCommand::query()
            ->with(['dataSource:id,name', 'requestedBy:id,name', 'branch:id,name,code'])
            ->latest('id');

        if ($activeBranchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $activeBranchId));
        } elseif (is_array($accessibleBranchIds)) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds));
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $targets = $this->loadMappedTargets($tenantContext);
        $preview = $this->computeRiskPreview($targets);

        $selectedCommand = null;
        if ($this->showDetailDrawer && $this->selectedCommandId !== null) {
            $candidate = RemoteCommand::query()
                ->with(['dataSource', 'requestedBy', 'branch', 'approvals.approver', 'attempts'])
                ->find($this->selectedCommandId);

            if ($candidate !== null && Gate::allows('view', $candidate)) {
                $selectedCommand = $candidate;
            }
        }

        $writeSources = DataSource::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->filter(fn (DataSource $s): bool => ! empty($s->capabilities_json['supports_write']));

        return view('livewire.command-center-workspace', [
            'commands' => $query->paginate(12),
            'writeSources' => $writeSources,
            'targets' => $targets,
            'preview' => $preview,
            'selectedCommand' => $selectedCommand,
            'canRequest' => $tenantContext->hasPermission('commands.request'),
            'canApprove' => $tenantContext->hasPermission('commands.approve'),
            'decimalHelper' => DecimalMath::class,
        ]);
    }

    private function selectDefaultTarget(TenantContext $tenantContext): void
    {
        $targets = $this->loadMappedTargets($tenantContext);
        $this->targetInternalId = $targets[0]['id'] ?? null;
    }

    /**
     * @return list<array{id: int, label: string, current_value: string}>
     */
    private function loadMappedTargets(TenantContext $tenantContext): array
    {
        if ($this->dataSourceId === null) {
            return [];
        }

        $accessibleBranchIds = $tenantContext->accessibleBranchIds();
        $activeBranchId = $tenantContext->activeBranchId();

        if ($this->commandType === 'product.price.update') {
            $mappedIds = ExternalMapping::query()
                ->where('data_source_id', $this->dataSourceId)
                ->where('canonical_entity', 'product')
                ->pluck('internal_id')
                ->all();

            return Product::query()
                ->whereIn('id', $mappedIds === [] ? [-1] : $mappedIds)
                ->orderBy('id')
                ->get()
                ->map(fn (Product $p): array => [
                    'id' => $p->id,
                    'label' => "{$p->sku} — {$p->name} (".DecimalMath::formatDisplay((string) $p->base_price, 2).')',
                    'current_value' => DecimalMath::normalize((string) $p->base_price),
                ])
                ->all();
        }

        if ($this->commandType === 'order.status.update') {
            $mappedIds = ExternalMapping::query()
                ->where('data_source_id', $this->dataSourceId)
                ->where('canonical_entity', 'order')
                ->pluck('internal_id')
                ->all();

            return Order::query()
                ->whereIn('id', $mappedIds === [] ? [-1] : $mappedIds)
                ->when($activeBranchId !== null, fn ($q) => $q->where('branch_id', $activeBranchId))
                ->when($activeBranchId === null && is_array($accessibleBranchIds), fn ($q) => $q->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds))
                ->latest('id')
                ->limit(40)
                ->get()
                ->map(fn (Order $o): array => [
                    'id' => $o->id,
                    'label' => "{$o->order_number} ({$o->order_status} / {$o->payment_status})",
                    'current_value' => $o->payment_status === 'partial' ? 'partial' : ($o->payment_status === 'unpaid' ? 'unpaid' : ($o->payment_status === 'refunded' ? 'refunded' : ($o->order_status === 'cancelled' ? 'cancelled' : 'completed'))),
                ])
                ->all();
        }

        $mappedIds = ExternalMapping::query()
            ->where('data_source_id', $this->dataSourceId)
            ->where('canonical_entity', 'inventory_balance')
            ->pluck('internal_id')
            ->all();

        return InventoryBalance::query()
            ->with(['product:id,sku,name', 'warehouse:id,branch_id,code,name'])
            ->whereIn('id', $mappedIds === [] ? [-1] : $mappedIds)
            ->whereHas('warehouse', function ($q) use ($activeBranchId, $accessibleBranchIds): void {
                if ($activeBranchId !== null) {
                    $q->where('branch_id', $activeBranchId);
                } elseif (is_array($accessibleBranchIds)) {
                    $q->whereIn('branch_id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
                }
            })
            ->orderBy('id')
            ->limit(40)
            ->get()
            ->map(fn (InventoryBalance $b): array => [
                'id' => $b->id,
                'label' => ($b->product?->sku ?? '').' @ '.($b->warehouse?->code ?? '').' ('.DecimalMath::formatDisplay((string) $b->on_hand, 2).')',
                'current_value' => DecimalMath::normalize((string) $b->on_hand),
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, label: string, current_value: string}>  $targets
     * @return array{current_value: string|null, risk_level: string, requires_approval: bool}
     */
    private function computeRiskPreview(array $targets): array
    {
        $selected = collect($targets)->firstWhere('id', $this->targetInternalId);
        $currentVal = $selected['current_value'] ?? null;

        if ($this->commandType === 'inventory.stock.adjust') {
            return ['current_value' => $currentVal, 'risk_level' => 'high', 'requires_approval' => true];
        }

        if ($this->commandType === 'order.status.update') {
            $high = in_array($this->newOrderStatus, ['cancelled', 'refunded'], true);

            return ['current_value' => $currentVal, 'risk_level' => $high ? 'high' : 'low', 'requires_approval' => $high];
        }

        if ($currentVal !== null && is_numeric($this->newPrice) && DecimalMath::gt($this->newPrice, '0')) {
            $cur = DecimalMath::normalize($currentVal);
            $newP = DecimalMath::normalize($this->newPrice);
            $diff = DecimalMath::gt($newP, $cur) ? DecimalMath::sub($newP, $cur) : DecimalMath::sub($cur, $newP);
            $high = DecimalMath::lte($cur, '0') || DecimalMath::gt(DecimalMath::mul(DecimalMath::div($diff, $cur), '100'), '15');

            return ['current_value' => $currentVal, 'risk_level' => $high ? 'high' : 'low', 'requires_approval' => $high];
        }

        return ['current_value' => $currentVal, 'risk_level' => 'low', 'requires_approval' => false];
    }
}
