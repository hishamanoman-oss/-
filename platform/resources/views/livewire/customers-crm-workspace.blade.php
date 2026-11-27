<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.customers_title') }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.customers_subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.kpi-card :label="__('ui.customers_total')" :value="$intel['total_customers']" accent="sky" />
        <x-ui.kpi-card :label="__('ui.active_customers')" :value="$intel['active_customers_count']" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.repeat_customers')" :value="$intel['repeat_customers_count']" accent="amber" />
        <x-ui.kpi-card :label="__('ui.outstanding_total')" :value="$decimalHelper::formatDisplay($intel['total_outstanding_receivables'], 2).' '.$currency" accent="rose" />
    </div>

    <x-ui.data-table :empty="empty($intel['customers'])" :empty-message="__('ui.no_customers_found')">
        <x-slot:toolbar>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('ui.search_placeholder') }}"
                data-testid="customers-search"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 sm:max-w-xs"
            >
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.customer') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.contact') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.orders_count') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.lifetime_value') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.outstanding_total') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.last_order_date') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.external_ref') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($intel['customers'] as $cust)
            <tr data-testid="customer-row-{{ $cust['code'] }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-medium text-slate-900 dark:text-white">{{ $cust['name'] }}</span>
                        @if ($cust['is_repeat_buyer'])
                            <x-ui.status-badge variant="live" :dot="false">{{ __('ui.repeat_buyer') }}</x-ui.status-badge>
                        @endif
                    </div>
                    <span class="block font-mono text-[11px] text-slate-400">{{ $cust['code'] }}</span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                    <span class="block font-mono">{{ $cust['phone'] ?? '—' }}</span>
                    <span class="block text-[11px]">{{ $cust['email'] ?? '' }}</span>
                </td>
                <td class="px-4 py-3 text-end font-mono text-xs font-semibold">{{ $cust['orders_count'] }}</td>
                <td class="px-4 py-3 text-end font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                    {{ $decimalHelper::formatDisplay($cust['lifetime_value'], 2) }} {{ $currency }}
                </td>
                <td class="px-4 py-3 text-end font-mono text-xs text-amber-600 dark:text-amber-400">
                    {{ $decimalHelper::formatDisplay($cust['outstanding_total'], 2) }}
                </td>
                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $cust['last_order_at'] ?? '—' }}</td>
                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $cust['external_id'] ? '#'.$cust['external_id'] : '—' }}</td>
                <td class="px-4 py-3 text-end">
                    <button
                        type="button"
                        wire:click="openCustomer({{ $cust['customer_id'] }})"
                        data-testid="view-customer-{{ $cust['code'] }}"
                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        {{ __('ui.profile') }}
                    </button>
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    {{-- Customer Profile Drawer --}}
    <x-ui.drawer :show="$showCustomerDrawer && $selectedCustomer !== null" :title="__('ui.customer_profile')" close-action="$set('showCustomerDrawer', false)">
        @if ($selectedCustomer)
            <div class="space-y-4 text-xs">
                <div class="border-b border-slate-200 pb-3 dark:border-slate-800">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $selectedCustomer->name }}</p>
                    <p class="font-mono text-slate-500">{{ $selectedCustomer->code }} • {{ $selectedCustomer->phone ?? '—' }} • {{ $selectedCustomer->email ?? '—' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 font-mono dark:bg-slate-800/60">
                    <div>{{ __('ui.lifetime_value') }}: {{ $decimalHelper::formatDisplay($selectedSummary['lifetime_value'], 2) }} {{ $currency }}</div>
                    <div>{{ __('ui.paid_total') }}: {{ $decimalHelper::formatDisplay($selectedSummary['paid_total'], 2) }} {{ $currency }}</div>
                    <div>{{ __('ui.outstanding_total') }}: {{ $decimalHelper::formatDisplay($selectedSummary['outstanding_total'], 2) }} {{ $currency }}</div>
                    <div>{{ __('ui.refunds') }}: {{ $decimalHelper::formatDisplay($selectedSummary['refunded_total'], 2) }} {{ $currency }}</div>
                </div>

                <div>
                    <p class="mb-2 font-bold text-slate-700 dark:text-slate-200">{{ __('ui.customer_orders_history') }}</p>
                    <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-start">
                            <thead class="bg-slate-50 dark:bg-slate-800/70">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ __('ui.order_number') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('ui.branch') }}</th>
                                    <th class="px-3 py-2 text-end">{{ __('ui.net_sales') }}</th>
                                    <th class="px-3 py-2 text-start">{{ __('ui.payment_status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @forelse ($selectedOrders as $ord)
                                    <tr>
                                        <td class="px-3 py-2 font-mono font-semibold">
                                            <a href="{{ route('orders.invoice', $ord) }}" class="text-sky-600 hover:underline dark:text-sky-400">{{ $ord->order_number }}</a>
                                        </td>
                                        <td class="px-3 py-2">{{ $ord->branch?->name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $ord->net_sales, 2) }}</td>
                                        <td class="px-3 py-2">{{ $ord->payment_status }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-3 py-4 text-center text-slate-400">{{ __('ui.no_orders_found') }}</td>
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
