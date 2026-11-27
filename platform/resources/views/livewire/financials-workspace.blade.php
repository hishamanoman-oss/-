<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.financials_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.financials_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="period" data-testid="financials-period-select" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold dark:border-slate-700 dark:bg-slate-950">
                <option value="today">{{ __('ui.period_today') }}</option>
                <option value="7d">{{ __('ui.period_7d') }}</option>
                <option value="30d">{{ __('ui.period_30d') }}</option>
                <option value="this_month">{{ __('ui.period_this_month') }}</option>
                <option value="custom">{{ __('ui.period_custom') }}</option>
            </select>
            @if ($period === 'custom')
                <input type="date" wire:model.live="customFrom" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                <input type="date" wire:model.live="customTo" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
            @endif
        </div>
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
        <div data-testid="financials-profit-guard" class="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-bold">{{ __('ui.profit_guard_title') }} ({{ $guardStatus }})</p>
                <p class="mt-0.5 text-xs opacity-90">{{ $guardMessage }}</p>
            </div>
            <span class="shrink-0 font-mono text-xs font-semibold">{{ __('ui.net_profit') }}: {{ __('ui.profit_unavailable') }}</span>
        </div>
    @endif

    {{-- Separated 9/10 Financial Metrics Strip --}}
    <div data-testid="financials-metrics-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-ui.kpi-card :label="__('ui.gross_sales')" :value="$decimalHelper::formatDisplay($summary['gross_sales'], 2).' '.$currency" accent="sky" />
        <x-ui.kpi-card :label="__('ui.discounts')" :value="$decimalHelper::formatDisplay($summary['discounts'], 2).' '.$currency" accent="amber" />
        <x-ui.kpi-card :label="__('ui.refunds')" :value="$decimalHelper::formatDisplay($summary['refunds'], 2).' '.$currency" accent="rose" />
        <x-ui.kpi-card :label="__('ui.net_sales')" :value="$decimalHelper::formatDisplay($summary['net_sales'], 2).' '.$currency" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.tax_total')" :value="$decimalHelper::formatDisplay($summary['tax_total'], 2).' '.$currency" accent="sky" />
        <x-ui.kpi-card :label="__('ui.paid_total')" :value="$decimalHelper::formatDisplay($summary['paid_total'], 2).' '.$currency" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.outstanding_total')" :value="$decimalHelper::formatDisplay($summary['outstanding_total'], 2).' '.$currency" accent="amber" />
        <x-ui.kpi-card :label="__('ui.cogs_total')" :value="$summary['cogs_total'] !== null ? $decimalHelper::formatDisplay($summary['cogs_total'], 2).' '.$currency : __('ui.profit_unavailable')" accent="sky" />
        <x-ui.kpi-card :label="__('ui.gross_profit')" :value="$summary['gross_profit'] !== null ? $decimalHelper::formatDisplay($summary['gross_profit'], 2).' '.$currency : __('ui.profit_unavailable')" accent="emerald" />
        <x-ui.kpi-card :label="__('ui.net_profit')" :value="$summary['net_profit'] !== null ? $decimalHelper::formatDisplay($summary['net_profit'], 2).' '.$currency : __('ui.profit_unavailable')" accent="rose" />
    </div>

    {{-- Branch-by-Branch Financial Comparison --}}
    <section class="space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.branch_financial_comparison') }}</h2>
        <x-ui.data-table :empty="empty($branchBreakdown)">
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('ui.branch') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.orders_count') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.gross_sales') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.discounts') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.refunds') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.net_sales') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.tax_total') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.paid_total') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.outstanding_total') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($branchBreakdown as $bb)
                <tr data-testid="financial-branch-row-{{ $bb['branch_code'] }}">
                    <td class="px-4 py-3 text-xs font-semibold">{{ $bb['branch_name'] }} <span class="font-mono text-[11px] text-slate-400">({{ $bb['branch_code'] }})</span></td>
                    <td class="px-4 py-3 text-end font-mono text-xs">{{ $bb['orders_count'] }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($bb['gross_sales'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs text-amber-600">{{ $decimalHelper::formatDisplay($bb['discounts'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs text-rose-600">{{ $decimalHelper::formatDisplay($bb['refunds'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">{{ $decimalHelper::formatDisplay($bb['net_sales'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($bb['tax_total'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($bb['paid_total'], 2) }}</td>
                    <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($bb['outstanding_total'], 2) }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </section>

    {{-- Payments & Refunds Ledger --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.payments_history') }}</h2>
            <x-ui.data-table :empty="$payments->isEmpty()">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.order_number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.branch') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.method') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.amount') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.timestamp') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($payments as $pay)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs font-semibold">
                            @if ($pay->order)
                                <a href="{{ route('orders.invoice', $pay->order) }}" data-testid="invoice-link-{{ $pay->order->order_number }}" class="text-sky-600 hover:underline dark:text-sky-400">
                                    {{ $pay->order->order_number }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ $pay->order?->branch?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $pay->method }}</td>
                        <td class="px-4 py-2.5 text-end font-mono text-xs text-emerald-600">{{ $decimalHelper::formatDisplay((string) $pay->amount, 2) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $pay->paid_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.refunds_history') }}</h2>
            <x-ui.data-table :empty="$refunds->isEmpty()" :empty-message="__('ui.no_refunds_found')">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.order_number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.reason') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.amount') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.timestamp') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($refunds as $ref)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs font-semibold">
                            @if ($ref->order)
                                <a href="{{ route('orders.invoice', $ref->order) }}" class="text-sky-600 hover:underline dark:text-sky-400">
                                    {{ $ref->order->order_number }}
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-xs">{{ $ref->reason ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-end font-mono text-xs text-rose-600">{{ $decimalHelper::formatDisplay((string) $ref->amount, 2) }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $ref->refunded_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </section>
    </div>
</div>
