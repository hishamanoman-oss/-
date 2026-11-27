<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.products_title') }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.products_subtitle') }}</p>
    </div>

    <x-ui.data-table :empty="$products->isEmpty()" :empty-message="__('ui.no_products_found')">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('ui.search_placeholder') }}"
                    data-testid="products-search"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 sm:max-w-xs"
                >

                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model.live="categoryFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="all">{{ __('ui.all_categories') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="statusFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="all">{{ __('ui.all_statuses') }}</option>
                        <option value="active">{{ __('ui.active') }}</option>
                        <option value="inactive">{{ __('ui.inactive') }}</option>
                    </select>
                </div>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.product_sku') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.category') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.base_price') }}</th>
                @if ($canViewCost)
                    <th class="px-4 py-3 text-end" data-testid="cost-price-header">{{ __('ui.cost_price') }}</th>
                @endif
                <th class="px-4 py-3 text-end">{{ __('ui.on_hand') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.available') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.external_ref') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($products as $product)
            @php
                $tot = $stockTotals[$product->id] ?? ['on_hand' => '0.0000', 'reserved' => '0.0000', 'available' => '0.0000'];
                $extId = $externalRefs[$product->id] ?? null;
            @endphp
            <tr data-testid="product-row-{{ $product->sku }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3">
                    <span class="font-medium text-slate-900 dark:text-white">{{ $product->name }}</span>
                    <span class="block font-mono text-[11px] text-slate-400">{{ $product->sku }}</span>
                </td>
                <td class="px-4 py-3 text-xs">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$product->status === 'active' ? 'active' : 'inactive'">
                        {{ __('ui.'.$product->status) }}
                    </x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end font-mono text-xs font-semibold">{{ $decimalHelper::formatDisplay((string) $product->base_price, 2) }} {{ $product->currency_code }}</td>
                @if ($canViewCost)
                    <td class="px-4 py-3 text-end font-mono text-xs" data-testid="product-cost-{{ $product->sku }}">
                        {{ $product->cost_price !== null ? $decimalHelper::formatDisplay((string) $product->cost_price, 2) : __('ui.missing_cost_badge') }}
                    </td>
                @endif
                <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($tot['on_hand'], 2) }}</td>
                <td class="px-4 py-3 text-end font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $decimalHelper::formatDisplay($tot['available'], 2) }}</td>
                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $extId ? '#'.$extId : '—' }}</td>
                <td class="px-4 py-3 text-end">
                    <button
                        type="button"
                        wire:click="openProduct({{ $product->id }})"
                        data-testid="view-product-{{ $product->sku }}"
                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        {{ __('ui.details') }}
                    </button>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $products->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    {{-- Product Detail Drawer --}}
    <x-ui.drawer :show="$showProductDrawer && $selectedProduct !== null" :title="__('ui.product_details')" close-action="$set('showProductDrawer', false)">
        @if ($selectedProduct)
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $selectedProduct->name }}</p>
                        <p class="font-mono text-slate-500">{{ $selectedProduct->sku }} • {{ $selectedProduct->category?->name ?? '—' }}</p>
                    </div>
                    <x-ui.status-badge :variant="$selectedProduct->status === 'active' ? 'active' : 'inactive'">{{ __('ui.'.$selectedProduct->status) }}</x-ui.status-badge>
                </div>

                <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 font-mono dark:bg-slate-800/60">
                    <div>{{ __('ui.base_price') }}: {{ $decimalHelper::formatDisplay((string) $selectedProduct->base_price, 2) }}</div>
                    @if ($canViewCost)
                        <div data-testid="drawer-product-cost">{{ __('ui.cost_price') }}: {{ $selectedProduct->cost_price !== null ? $decimalHelper::formatDisplay((string) $selectedProduct->cost_price, 2) : __('ui.missing_cost_badge') }}</div>
                    @endif
                    <div>{{ __('ui.external_ref') }}: {{ $selectedExternalRef ?? '—' }}</div>
                    <div>{{ __('ui.source_updated') }}: {{ $selectedProduct->source_updated_at?->format('Y-m-d H:i') ?? '—' }}</div>
                </div>

                <div>
                    <p class="mb-2 font-bold text-slate-700 dark:text-slate-200">{{ __('ui.warehouse_breakdown') }}</p>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-start">
                            <thead class="bg-slate-50 dark:bg-slate-800/70">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ __('ui.warehouse') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.on_hand') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.reserved') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.available') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @forelse ($selectedWarehouseBalances as $wb)
                                    <tr>
                                        <td class="px-3 py-2">{{ $wb->warehouse->name }} ({{ $wb->warehouse->code }})</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $wb->on_hand, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono text-amber-600">{{ $decimalHelper::formatDisplay((string) $wb->reserved, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-semibold text-emerald-600">{{ $decimalHelper::formatDisplay((string) $wb->available, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-center text-slate-400">{{ __('ui.no_inventory_found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </x-ui.drawer>
</div>
