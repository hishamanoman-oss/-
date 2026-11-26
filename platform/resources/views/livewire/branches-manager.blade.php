<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.branches_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.branches_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canManageWarehouses && $availableBranches->isNotEmpty())
                <button
                    type="button"
                    wire:click="openCreateWarehouse"
                    data-testid="add-warehouse-btn"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                    + {{ __('ui.add_warehouse') }}
                </button>
            @endif

            @if ($canCreateBranch)
                <button
                    type="button"
                    wire:click="openCreateBranch"
                    data-testid="add-branch-btn"
                    class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
                >
                    + {{ __('ui.add_branch') }}
                </button>
            @endif
        </div>
    </div>

    <x-ui.data-table :empty="$branches->isEmpty()" :empty-message="__('ui.no_branches_found')">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('ui.search_placeholder') }}"
                    data-testid="branches-search"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white sm:max-w-xs"
                >

                <select
                    wire:model.live="statusFilter"
                    data-testid="branches-status-filter"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                    <option value="all">{{ __('ui.all_statuses') }}</option>
                    <option value="active">{{ __('ui.active') }}</option>
                    <option value="inactive">{{ __('ui.inactive') }}</option>
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">
                    <button type="button" wire:click="sortBy('code')" class="font-semibold hover:text-sky-600">{{ __('ui.code') }}</button>
                </th>
                <th class="px-4 py-3 text-start">
                    <button type="button" wire:click="sortBy('name')" class="font-semibold hover:text-sky-600">{{ __('ui.name') }}</button>
                </th>
                <th class="px-4 py-3 text-start">
                    <button type="button" wire:click="sortBy('city')" class="font-semibold hover:text-sky-600">{{ __('ui.city') }}</button>
                </th>
                <th class="px-4 py-3 text-start">{{ __('ui.warehouses') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($branches as $branch)
            <tr data-testid="branch-row-{{ $branch->code }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $branch->code }}</td>
                <td class="px-4 py-3 font-medium">{{ $branch->name }}</td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $branch->city }}</td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($branch->warehouses as $wh)
                            <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs dark:border-slate-700 dark:bg-slate-800">
                                <span class="font-mono text-[11px]">{{ $wh->code }}</span>
                                <span>{{ $wh->name }}</span>
                                @if (\Illuminate\Support\Facades\Gate::allows('update', $wh))
                                    <button
                                        type="button"
                                        wire:click="toggleWarehouseStatus({{ $wh->id }})"
                                        class="ms-1 text-[10px] font-semibold {{ $wh->status === 'active' ? 'text-emerald-600' : 'text-rose-500' }}"
                                    >
                                        ({{ __('ui.'.$wh->status) }})
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$branch->status">{{ __('ui.'.$branch->status) }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <div class="inline-flex items-center gap-2">
                        @if (\Illuminate\Support\Facades\Gate::allows('update', $branch))
                            <button
                                type="button"
                                wire:click="openEditBranch({{ $branch->id }})"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            >
                                {{ __('ui.edit') }}
                            </button>
                            <button
                                type="button"
                                wire:click="toggleBranchStatus({{ $branch->id }})"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            >
                                {{ $branch->status === 'active' ? __('ui.deactivate') : __('ui.activate') }}
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $branches->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    {{-- Branch Modal --}}
    <x-ui.drawer :show="$showBranchModal" :title="$editingBranchId ? __('ui.edit_branch') : __('ui.add_branch')" close-action="$set('showBranchModal', false)">
        <form wire:submit="saveBranch" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.code') }}</label>
                    <input type="text" wire:model="branchCode" data-testid="branch-code-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('branchCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.status') }}</label>
                    <select wire:model="branchStatus" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="active">{{ __('ui.active') }}</option>
                        <option value="inactive">{{ __('ui.inactive') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.name') }}</label>
                <input type="text" wire:model="branchName" data-testid="branch-name-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('branchName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.city') }}</label>
                    <input type="text" wire:model="branchCity" data-testid="branch-city-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('branchCity') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.timezone') }}</label>
                    <input type="text" wire:model="branchTimezone" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showBranchModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" data-testid="branch-save-btn" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>

    {{-- Warehouse Modal --}}
    <x-ui.drawer :show="$showWarehouseModal" :title="__('ui.add_warehouse')" close-action="$set('showWarehouseModal', false)">
        <form wire:submit="saveWarehouse" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.select_branch') }}</label>
                <select wire:model="warehouseBranchId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">--</option>
                    @foreach ($availableBranches as $ab)
                        <option value="{{ $ab->id }}">{{ $ab->name }} ({{ $ab->code }})</option>
                    @endforeach
                </select>
                @error('warehouseBranchId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.code') }}</label>
                    <input type="text" wire:model="warehouseCode" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('warehouseCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.status') }}</label>
                    <select wire:model="warehouseStatus" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="active">{{ __('ui.active') }}</option>
                        <option value="inactive">{{ __('ui.inactive') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.name') }}</label>
                <input type="text" wire:model="warehouseName" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('warehouseName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model="warehouseIsDefault" class="rounded border-slate-300">
                <span>{{ __('ui.default_warehouse') }}</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showWarehouseModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>
</div>
