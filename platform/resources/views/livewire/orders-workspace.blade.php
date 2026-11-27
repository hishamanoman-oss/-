<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.orders_title') }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.orders_subtitle') }}</p>
    </div>

    {{-- Separated Financial Metrics Strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-ui.kpi-card :label="__('ui.gross_sales')" :value="$decimalHelper::formatDisplay($summary['gross_sales']).' '.$currency" accent="sky" />
        <x-ui.kpi-card :label="__('ui.discounts')" :value="$decimalHelper::formatDisplay($summary['discounts']).' '.$currency" accent="amber" />
        <x-ui.kpi-card :label="__('ui.refunds')" :value="$decimalHelper::formatDisplay($summary['refunds']).' '.$currency" accent="rose" />
        <x-ui.kpi-card :label="__('ui.net_sales')" :value="$decimalHelper::formatDisplay($summary['net_sales']).' '.$currency" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.paid_total')" :value="$decimalHelper::formatDisplay($summary['paid_total']).' '.$currency" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.outstanding_total')" :value="$decimalHelper::formatDisplay($summary['outstanding_total']).' '.$currency" accent="amber" />
    </div>

    {{-- Net Profit Completeness Guard Banner --}}
    @php
        $guardStatus = $summary['profit_completeness_status'];
        $guardMessage = match ($guardStatus) {
            'missing_cost' => __('ui.profit_guard_missing_cost'),
            'missing_expenses' => __('ui.profit_guard_missing_expenses'),
            'incomplete' => __('ui.profit_guard_incomplete'),
            default => null,
        };
    @endphp
    @if ($guardMessage !== null)
        <div data-testid="profit-completeness-guard" class="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-bold">{{ __('ui.profit_guard_title') }} ({{ $guardStatus }})</p>
                <p class="mt-0.5 text-xs opacity-90">{{ $guardMessage }}</p>
            </div>
            <div class="shrink-0 font-mono text-xs font-semibold">
                {{ __('ui.net_profit') }}: {{ __('ui.profit_unavailable') }}
            </div>
        </div>
    @endif

    <x-ui.data-table :empty="$orders->isEmpty()" :empty-message="__('ui.no_orders_found')">
        <x-slot:toolbar>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('ui.search_placeholder') }}"
                    data-testid="orders-search"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 sm:max-w-xs"
                >

                <select
                    wire:model.live="statusFilter"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                >
                    <option value="all">{{ __('ui.all_statuses') }}</option>
                    <option value="pending">pending</option>
                    <option value="confirmed">confirmed</option>
                    <option value="completed">completed</option>
                    <option value="cancelled">cancelled</option>
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.order_number') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.branch') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.customer') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.order_status') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.payment_status') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.fulfillment_status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.net_sales') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.paid_total') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.outstanding_total') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($orders as $order)
            @php
                $payVariant = match ($order->payment_status) {
                    'paid' => 'active',
                    'partial', 'partially_refunded' => 'delayed',
                    'refunded' => 'error',
                    default => 'disconnected',
                };
            @endphp
            <tr data-testid="order-row-{{ $order->order_number }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3 font-mono text-xs font-bold">{{ $order->order_number }}</td>
                <td class="px-4 py-3 text-xs">{{ $order->branch->name }}</td>
                <td class="px-4 py-3 text-xs">{{ $order->customer?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$order->order_status === 'completed' ? 'active' : 'info'" :dot="false">{{ $order->order_status }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$payVariant" :dot="false">{{ $order->payment_status }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.status-badge variant="neutral" :dot="false">{{ $order->fulfillment_status }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end font-mono font-semibold">{{ $decimalHelper::formatDisplay((string) $order->net_sales) }}</td>
                <td class="px-4 py-3 text-end font-mono text-emerald-600 dark:text-emerald-400">{{ $decimalHelper::formatDisplay((string) $order->paid_total) }}</td>
                <td class="px-4 py-3 text-end font-mono text-amber-600 dark:text-amber-400">{{ $decimalHelper::formatDisplay((string) $order->outstanding_total) }}</td>
                <td class="px-4 py-3 text-end">
                    <button
                        type="button"
                        wire:click="openOrder({{ $order->id }})"
                        data-testid="view-order-{{ $order->order_number }}"
                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        {{ __('ui.order_details') }}
                    </button>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $orders->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    {{-- Order Detail & Immutable Snapshots Drawer --}}
    <x-ui.drawer :show="$showOrderDrawer && $selectedOrder !== null" :title="__('ui.order_details')" close-action="$set('showOrderDrawer', false)">
        @if ($selectedOrder)
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div>
                        <p class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $selectedOrder->order_number }}</p>
                        <p class="text-slate-500">{{ $selectedOrder->branch->name }} • {{ $selectedOrder->placed_at?->format('Y-m-d H:i') }}</p>
                    </div>
                    <x-ui.status-badge variant="info" :dot="false">{{ $selectedOrder->currency_code }}</x-ui.status-badge>
                </div>

                <div class="flex justify-end">
                    <a
                        href="{{ route('orders.invoice', $selectedOrder) }}"
                        data-testid="open-invoice-link-{{ $selectedOrder->order_number }}"
                        class="rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-600"
                    >
                        {{ __('ui.open_printable_invoice') }}
                    </a>
                </div>

                <div>
                    <p class="mb-2 font-bold text-slate-700 dark:text-slate-200">{{ __('ui.order_items_snapshot') }}</p>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-start">
                            <thead class="bg-slate-50 dark:bg-slate-800/70">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ __('ui.product_sku') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.quantity') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.unit_price') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($selectedOrder->items as $item)
                                    <tr data-testid="order-item-snapshot-{{ $item->sku_snapshot }}">
                                        <td class="px-3 py-2">
                                            <span class="font-medium">{{ $item->product_name_snapshot }}</span>
                                            <span class="block font-mono text-[11px] text-slate-400">{{ $item->sku_snapshot }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $item->quantity, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $item->unit_price_snapshot, 2) }}</td>
                                        <td class="px-3 py-2 text-end font-mono font-semibold">{{ $decimalHelper::formatDisplay((string) $item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 font-mono dark:bg-slate-800/60">
                    <div>{{ __('ui.gross_sales') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->gross_sales) }}</div>
                    <div>{{ __('ui.discounts') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->discount_total) }}</div>
                    <div>{{ __('ui.tax_total') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->tax_total) }}</div>
                    <div>{{ __('ui.net_sales') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->net_sales) }}</div>
                    <div>{{ __('ui.paid_total') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->paid_total) }}</div>
                    <div>{{ __('ui.refunds') }}: {{ $decimalHelper::formatDisplay((string) $selectedOrder->refunded_total) }}</div>
                </div>
            </div>
        @endif
    </x-ui.drawer>
</div>
