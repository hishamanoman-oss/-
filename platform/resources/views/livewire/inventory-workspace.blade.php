<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.inventory_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.inventory_subtitle') }}</p>
        </div>

        @if ($canAdjust && $warehouses->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                @if ($warehouses->count() >= 2)
                    <button
                        type="button"
                        wire:click="openTransferModal"
                        data-testid="open-transfer-btn"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        {{ __('ui.stock_transfer') }}
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="openAdjustModal"
                    data-testid="open-adjust-btn"
                    class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
                >
                    + {{ __('ui.stock_adjust') }}
                </button>
            </div>
        @endif
    </div>

    <x-ui.data-table :empty="$balances->isEmpty()" :empty-message="__('ui.no_inventory_found')">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('ui.search_placeholder') }}"
                    data-testid="inventory-search"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:max-w-xs"
                >

                <select
                    wire:model.live="warehouseFilter"
                    data-testid="inventory-warehouse-filter"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                    <option value="all">{{ __('ui.all_warehouses') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->code }})</option>
                    @endforeach
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.product_sku') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.warehouse') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.branch') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.on_hand') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.reserved') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.available') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.reorder_status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($balances as $bal)
            @php
                $isLow = $decimalHelper::lte((string) $bal->available, (string) $bal->product->reorder_point);
            @endphp
            <tr data-testid="inventory-row-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3">
                    <p class="font-medium text-slate-900 dark:text-white">{{ $bal->product->name }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ $bal->product->sku }}</p>
                </td>
                <td class="px-4 py-3 text-xs">
                    <span class="font-medium">{{ $bal->warehouse->name }}</span>
                    <span class="block font-mono text-[11px] text-slate-400">{{ $bal->warehouse->code }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $bal->warehouse->branch->name }}</td>
                <td data-testid="on-hand-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}" class="px-4 py-3 text-end font-mono font-semibold">{{ $decimalHelper::formatDisplay((string) $bal->on_hand, 2) }}</td>
                <td data-testid="reserved-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}" class="px-4 py-3 text-end font-mono text-amber-600 dark:text-amber-400">{{ $decimalHelper::formatDisplay((string) $bal->reserved, 2) }}</td>
                <td data-testid="available-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}" class="px-4 py-3 text-end font-mono font-bold text-emerald-700 dark:text-emerald-400">{{ $decimalHelper::formatDisplay((string) $bal->available, 2) }}</td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$isLow ? 'delayed' : 'live'">
                        {{ $isLow ? __('ui.stock_low') : __('ui.stock_ok') }}
                    </x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="openMovements({{ $bal->id }})"
                            data-testid="movements-btn-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}"
                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                        >
                            {{ __('ui.view_movements') }}
                        </button>
                        @if ($canAdjust && \Illuminate\Support\Facades\Gate::allows('adjust', $bal->warehouse))
                            <button
                                type="button"
                                wire:click="openAdjustModal({{ $bal->warehouse_id }}, {{ $bal->product_id }})"
                                data-testid="adjust-btn-{{ $bal->product->sku }}-{{ $bal->warehouse->code }}"
                                class="rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-300"
                            >
                                {{ __('ui.stock_adjust') }}
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $balances->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    {{-- Immutable Stock Movements Drawer --}}
    <x-ui.drawer :show="$showMovementsDrawer" :title="__('ui.movements_history')" close-action="$set('showMovementsDrawer', false)">
        <div class="space-y-4">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $selectedLabel }}</p>
            <div class="max-h-96 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-start text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/70">
                        <tr>
                            <th class="px-3 py-2 text-start">{{ __('ui.movement_type') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('ui.quantity_delta') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('ui.balance_after') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('ui.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($movements as $mov)
                            <tr>
                                <td class="px-3 py-2 font-mono font-semibold">{{ $mov->movement_type }}</td>
                                <td class="px-3 py-2 text-end font-mono {{ $decimalHelper::lt((string) $mov->quantity_delta, '0') ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ $mov->quantity_delta }}
                                </td>
                                <td class="px-3 py-2 text-end font-mono">{{ $mov->balance_after }}</td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ $mov->notes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">{{ __('ui.no_movements_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-ui.drawer>

    {{-- Stock Adjustment Modal --}}
    <x-ui.drawer :show="$showAdjustModal" :title="__('ui.stock_adjust')" close-action="$set('showAdjustModal', false)">
        <form wire:submit="saveAdjustment" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.warehouse') }}</label>
                <select wire:model="adjustWarehouseId" data-testid="adjust-warehouse-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">--</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->code }})</option>
                    @endforeach
                </select>
                @error('adjustWarehouseId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.product') }}</label>
                <select wire:model="adjustProductId" data-testid="adjust-product-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">--</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                    @endforeach
                </select>
                @error('adjustProductId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.quantity_delta') }}</label>
                <input type="text" wire:model="adjustDelta" data-testid="adjust-delta-input" placeholder="{{ __('ui.quantity_delta_hint') }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('adjustDelta') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.notes') }}</label>
                <input type="text" wire:model="adjustNotes" data-testid="adjust-notes-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('adjustNotes') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showAdjustModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" data-testid="adjust-save-btn" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>

    {{-- Warehouse Transfer Modal --}}
    <x-ui.drawer :show="$showTransferModal" :title="__('ui.stock_transfer')" close-action="$set('showTransferModal', false)">
        <form wire:submit="saveTransfer" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.from_warehouse') }}</label>
                    <select wire:model="transferFromWarehouseId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="">--</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                    @error('transferFromWarehouseId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.to_warehouse') }}</label>
                    <select wire:model="transferToWarehouseId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="">--</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                    @error('transferToWarehouseId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.product') }}</label>
                    <select wire:model="transferProductId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="">--</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('transferProductId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.quantity') }}</label>
                    <input type="text" wire:model="transferQuantity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('transferQuantity') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.notes') }}</label>
                <input type="text" wire:model="transferNotes" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showTransferModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>
</div>
