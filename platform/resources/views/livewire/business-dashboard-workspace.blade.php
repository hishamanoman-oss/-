<div class="space-y-6">
    {{-- Header & Period Switcher --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ __('ui.welcome_back', ['name' => $user?->name ?? '']) }}
            </h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span>{{ $tenant?->name ?? '' }}</span>
                <span>•</span>
                <span>{{ __('ui.current_role') }}:</span>
                @foreach ($roles as $role)
                    <x-ui.status-badge variant="info" :dot="false">{{ $role->name }}</x-ui.status-badge>
                @endforeach
                <span>•</span>
                <span>{{ $activeBranch ? $activeBranch->name : __('ui.full_tenant_scope') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select
                wire:model.live="period"
                data-testid="dashboard-period-select"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
            >
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

    {{-- Role-Aware Primary KPI Grid (Every KPI drills down cleanly to its workspace) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @if ($focusRole === 'inventory')
            <a href="{{ route('inventory.index') }}" data-testid="drill-inventory-skus" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.total_skus')"
                    :value="$inventory['total_skus']"
                    :hint="__('ui.total_on_hand').': '.$decimalHelper::formatDisplay($inventory['total_on_hand'], 2)"
                    accent="sky"
                />
            </a>
            <a href="{{ route('inventory.index', ['stockFilter' => 'low_stock']) }}" data-testid="drill-low-stock" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.low_stock_alerts')"
                    :value="$inventory['low_stock_count']"
                    :hint="__('ui.out_of_stock_count').': '.$inventory['out_of_stock_count']"
                    accent="rose"
                    data-testid="kpi-low-stock"
                />
            </a>
            <a href="{{ route('inventory.index', ['stockFilter' => 'slow_moving']) }}" data-testid="drill-slow-moving" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.slow_moving_skus')"
                    :value="$inventory['slow_moving_count']"
                    :hint="__('ui.reserved').': '.$decimalHelper::formatDisplay($inventory['total_reserved'], 2)"
                    accent="amber"
                />
            </a>
            <a href="{{ route('organization.branches') }}" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.branches_total')"
                    :value="$branchesCount"
                    :hint="__('ui.warehouses_total').': '.count($inventory['warehouse_summary'])"
                    accent="emerald"
                    data-testid="kpi-branches-count"
                />
            </a>
        @elseif ($focusRole === 'accountant')
            <a href="{{ route('financials.index') }}" data-testid="drill-financials-net" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.net_sales')"
                    :value="$decimalHelper::formatDisplay($financials['net_sales'], 2).' '.$currency"
                    :hint="__('ui.gross_sales').': '.$decimalHelper::formatDisplay($financials['gross_sales'], 2)"
                    accent="emerald"
                    data-testid="kpi-net-sales"
                />
            </a>
            <a href="{{ route('financials.index') }}" data-testid="drill-financials-paid" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.paid_total')"
                    :value="$decimalHelper::formatDisplay($financials['paid_total'], 2).' '.$currency"
                    :hint="__('ui.tax_total').': '.$decimalHelper::formatDisplay($financials['tax_total'], 2)"
                    accent="sky"
                />
            </a>
            <a href="{{ route('orders.index') }}" data-testid="drill-financials-outstanding" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.outstanding_total')"
                    :value="$decimalHelper::formatDisplay($financials['outstanding_total'], 2).' '.$currency"
                    :hint="__('ui.refunds').': '.$decimalHelper::formatDisplay($financials['refunds'], 2)"
                    accent="amber"
                />
            </a>
            <a href="{{ route('organization.branches') }}" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.branches_total')"
                    :value="$branchesCount"
                    :hint="__('ui.orders_count').': '.$sales['orders_count']"
                    accent="sky"
                    data-testid="kpi-branches-count"
                />
            </a>
        @else
            @php
                $deltaLabel = $sales['delta_percent'] !== null
                    ? (($decimalHelper::gte($sales['delta_percent'], '0') ? '+' : '').$decimalHelper::formatDisplay($sales['delta_percent'], 1).'% '.__('ui.vs_previous_period'))
                    : __('ui.no_previous_period_data');
            @endphp
            <a href="{{ route('orders.index') }}" data-testid="drill-sales-orders" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.net_sales')"
                    :value="$decimalHelper::formatDisplay($sales['net_sales'], 2).' '.$currency"
                    :hint="$deltaLabel"
                    accent="emerald"
                    data-testid="kpi-net-sales"
                />
            </a>
            <a href="{{ route('financials.index') }}" data-testid="drill-financials" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.paid_total')"
                    :value="$decimalHelper::formatDisplay($sales['paid_total'], 2).' '.$currency"
                    :hint="__('ui.orders_count').': '.$sales['orders_count'].' • '.__('ui.avg_order_value').': '.$decimalHelper::formatDisplay($sales['average_order_value'], 2)"
                    accent="sky"
                    data-testid="kpi-orders-count"
                />
            </a>
            <a href="{{ route('inventory.index', ['stockFilter' => 'low_stock']) }}" data-testid="drill-inventory-alerts" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.low_stock_alerts')"
                    :value="$inventory['low_stock_count']"
                    :hint="__('ui.out_of_stock_count').': '.$inventory['out_of_stock_count'].' • '.__('ui.slow_moving_skus').': '.$inventory['slow_moving_count']"
                    accent="rose"
                    data-testid="kpi-low-stock"
                />
            </a>
            <a href="{{ route('organization.branches') }}" data-testid="drill-branches" class="block transition hover:scale-[1.01]">
                <x-ui.kpi-card
                    :label="__('ui.branches_total')"
                    :value="$branchesCount"
                    :hint="__('ui.active').': '.$activeBranchesCount.' • '.__('ui.customers_total').': '.$customers['total_customers']"
                    accent="amber"
                    data-testid="kpi-branches-count"
                />
            </a>
        @endif
    </div>

    {{-- Attention Needed Panel --}}
    <section data-testid="attention-needed-panel" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('ui.attention_needed') }}</h2>
            <div class="flex flex-wrap gap-2 text-xs">
                @if ($canViewSales)
                    <a href="{{ route('customers.index') }}" class="font-semibold text-sky-600 hover:underline dark:text-sky-400">{{ __('ui.nav_customers') }} ({{ $customers['total_customers'] }})</a>
                @endif
                @if ($canViewInventory)
                    <span>•</span>
                    <a href="{{ route('products.index') }}" class="font-semibold text-sky-600 hover:underline dark:text-sky-400">{{ __('ui.nav_products') }}</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            {{-- Low / Out of Stock Alert Box --}}
            <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs dark:border-rose-900/60 dark:bg-rose-950/30">
                <div class="flex items-center justify-between font-bold text-rose-800 dark:text-rose-200">
                    <span>{{ __('ui.low_stock_alerts') }}</span>
                    <a href="{{ route('inventory.index', ['stockFilter' => 'low_stock']) }}" class="underline">{{ $inventory['low_stock_count'] }}</a>
                </div>
                <ul class="mt-2 space-y-1 text-rose-700 dark:text-rose-300">
                    @forelse (array_slice($inventory['low_stock_items'], 0, 3) as $item)
                        <li class="truncate font-mono">{{ $item['sku'] }} ({{ $decimalHelper::formatDisplay($item['available'], 0) }} / {{ $item['warehouse_code'] }})</li>
                    @empty
                        <li class="text-slate-500">{{ __('ui.no_low_stock_alerts') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Delayed Unfulfilled Orders Box --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs dark:border-amber-900/60 dark:bg-amber-950/30">
                <div class="flex items-center justify-between font-bold text-amber-800 dark:text-amber-200">
                    <span>{{ __('ui.delayed_orders') }}</span>
                    @if ($canViewSales)
                        <a href="{{ route('orders.index') }}" class="underline">{{ $sales['delayed_orders_count'] }}</a>
                    @else
                        <span>{{ $sales['delayed_orders_count'] }}</span>
                    @endif
                </div>
                <ul class="mt-2 space-y-1 text-amber-700 dark:text-amber-300">
                    @forelse (array_slice($sales['delayed_orders'], 0, 3) as $dOrder)
                        <li class="truncate font-mono">{{ $dOrder['order_number'] }} — {{ $dOrder['branch_name'] }}</li>
                    @empty
                        <li class="text-slate-500">{{ __('ui.no_delayed_orders') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Data Freshness & Sync Health Box --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs dark:border-slate-800 dark:bg-slate-950">
                <div class="flex items-center justify-between font-bold text-slate-800 dark:text-slate-200">
                    <span>{{ __('ui.sync_health_alerts') }}</span>
                    @if ($canViewIntegrations)
                        <a href="{{ route('integrations.index') }}" class="text-sky-600 underline dark:text-sky-400">{{ count($syncAlerts) }}</a>
                    @else
                        <span>{{ count($syncAlerts) }}</span>
                    @endif
                </div>
                <ul class="mt-2 space-y-1 text-slate-600 dark:text-slate-400">
                    @forelse (array_slice($syncAlerts, 0, 3) as $sa)
                        <li class="truncate">{{ $sa['name'] }}: {{ __('ui.sync_'.$sa['freshness']) }}</li>
                    @empty
                        <li class="text-emerald-600 dark:text-emerald-400">{{ __('ui.all_sources_healthy') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    {{-- Top Branches & Top Products / Warehouses Breakdown --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Permitted Branches Performance --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.top_branches') }}</h2>
                <a href="{{ route('organization.branches') }}" class="text-xs font-semibold text-sky-600 hover:underline dark:text-sky-400">{{ __('ui.view_all') }}</a>
            </div>
            <x-ui.data-table :empty="$branchPerformance->isEmpty()">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.code') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.branch') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.orders_count') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.net_sales') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($branchPerformance as $bp)
                    <tr data-testid="dashboard-branch-row-{{ $bp['branch_id'] }}">
                        <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $bp['code'] }}</td>
                        <td class="px-4 py-3 text-xs font-medium">{{ $bp['name'] }}</td>
                        <td class="px-4 py-3 text-end font-mono text-xs">{{ $bp['orders_count'] }}</td>
                        <td class="px-4 py-3 text-end font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                            {{ $decimalHelper::formatDisplay($bp['net_sales'], 2) }} {{ $currency }}
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </section>

        {{-- Top Selling Products OR Warehouse Summary for Inventory Role --}}
        @if ($focusRole === 'inventory' || ! $canViewSales)
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.warehouse_balances_summary') }}</h2>
                    <a href="{{ route('inventory.index') }}" class="text-xs font-semibold text-sky-600 hover:underline dark:text-sky-400">{{ __('ui.view_all') }}</a>
                </div>
                <x-ui.data-table :empty="empty($inventory['warehouse_summary'])">
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('ui.warehouse') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.on_hand') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.available') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.low_stock_alerts') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($inventory['warehouse_summary'] as $ws)
                        <tr>
                            <td class="px-4 py-3 text-xs font-medium">{{ $ws['warehouse_name'] }} <span class="font-mono text-[11px] text-slate-400">({{ $ws['warehouse_code'] }})</span></td>
                            <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($ws['total_on_hand'], 2) }}</td>
                            <td class="px-4 py-3 text-end font-mono text-xs font-semibold">{{ $decimalHelper::formatDisplay($ws['total_available'], 2) }}</td>
                            <td class="px-4 py-3 text-end font-mono text-xs text-rose-600">{{ $ws['low_stock_count'] }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </section>
        @else
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.top_selling_products') }}</h2>
                    <a href="{{ route('products.index') }}" class="text-xs font-semibold text-sky-600 hover:underline dark:text-sky-400">{{ __('ui.view_all') }}</a>
                </div>
                <x-ui.data-table :empty="empty($sales['top_products'])" :empty-message="__('ui.no_sales_in_period')">
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('ui.product_sku') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.quantity_sold') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.net_sales') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($sales['top_products'] as $tp)
                        <tr>
                            <td class="px-4 py-3 text-xs">
                                <span class="font-medium">{{ $tp['name'] }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $tp['sku'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-end font-mono text-xs">{{ $decimalHelper::formatDisplay($tp['quantity_sold'], 2) }}</td>
                            <td class="px-4 py-3 text-end font-mono text-xs font-semibold text-sky-700 dark:text-sky-300">
                                {{ $decimalHelper::formatDisplay($tp['revenue_total'], 2) }} {{ $currency }}
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </section>
        @endif
    </div>
</div>
