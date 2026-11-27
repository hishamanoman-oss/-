@props([
    'title' => null,
])

@php
    $locale = $currentLocale ?? app()->getLocale();
    $dir = $textDirection ?? ($locale === 'ar' ? 'rtl' : 'ltr');
    $theme = $currentTheme ?? session('theme', 'light');
    $user = auth()->user();

    /** @var \App\Domain\Tenants\Services\TenantContext $ctx */
    $ctx = app(\App\Domain\Tenants\Services\TenantContext::class);
    $activeTenant = $ctx->tenant();
    $deploymentMode = (string) config('platform.deployment_mode', 'saas');

    $userMemberships = $user
        ? $ctx->runWithoutTenantScope(fn () => $user->activeMemberships()->with('tenant')->orderBy('id')->get())
        : collect();

    $accessibleBranchIds = $ctx->accessibleBranchIds();
    $headerBranches = collect();
    $syncHeaderStatus = 'disconnected';
    $syncHeaderLabel = __('ui.sync_disconnected');
    if ($ctx->id() !== null) {
        $branchQuery = \App\Domain\Organizations\Models\Branch::query()->orderBy('id');
        if (is_array($accessibleBranchIds)) {
            $branchQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        $headerBranches = $branchQuery->get();

        $evaluator = app(\App\Domain\Synchronization\Services\FreshnessEvaluator::class);
        $activeSources = \App\Domain\Integrations\Models\DataSource::query()
            ->whereIn('status', ['active', 'error'])
            ->get();

        if ($activeSources->isNotEmpty()) {
            $evaluatedStatuses = $activeSources->map(fn ($s): string => $evaluator->evaluate($s))->all();
            if (in_array('syncing', $evaluatedStatuses, true)) {
                $syncHeaderStatus = 'syncing';
            } elseif (in_array('error', $evaluatedStatuses, true)) {
                $syncHeaderStatus = 'error';
            } elseif (in_array('live', $evaluatedStatuses, true)) {
                $syncHeaderStatus = 'live';
            } elseif (in_array('delayed', $evaluatedStatuses, true)) {
                $syncHeaderStatus = 'delayed';
            } else {
                $syncHeaderStatus = 'disconnected';
            }
            $syncHeaderLabel = __('ui.sync_'.$syncHeaderStatus);
        }
    }

    $syncJsConfig = [
        'labels' => [
            'live' => __('ui.sync_live'),
            'delayed' => __('ui.sync_delayed'),
            'syncing' => __('ui.sync_syncing'),
            'disconnected' => __('ui.sync_disconnected'),
            'error' => __('ui.sync_error'),
        ],
        'toastTemplate' => __('ui.realtime_sync_toast'),
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="active-tenant-id" content="{{ $ctx->id() ?? '' }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ __('ui.app_name') }}</title>
    <script>
        window.__rbdSyncConfig = {{ \Illuminate\Support\Js::from($syncJsConfig) }};
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div x-data="{ mobileOpen: false, collapsed: false }" class="flex min-h-screen w-full overflow-x-hidden">
        {{-- Mobile Backdrop --}}
        <div
            x-show="mobileOpen"
            x-cloak
            x-on:click="mobileOpen = false"
            class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-xs lg:hidden"
        ></div>

        {{-- Sidebar --}}
        <aside
            :class="[
                mobileOpen ? 'translate-x-0' : (document.documentElement.dir === 'rtl' ? 'translate-x-full lg:translate-x-0' : '-translate-x-full lg:translate-x-0'),
                collapsed ? 'lg:w-20' : 'lg:w-64'
            ]"
            data-testid="app-sidebar"
            class="fixed inset-y-0 start-0 z-50 flex w-64 shrink-0 flex-col border-e border-slate-800 bg-slate-950 text-slate-200 transition-all duration-200 lg:static"
        >
            {{-- Brand --}}
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-800 px-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-600 font-bold text-white shadow-sm">
                        RB
                    </span>
                    <div x-show="!collapsed" class="min-w-0">
                        <p class="truncate text-sm font-bold text-white">{{ __('ui.app_name') }}</p>
                        <p class="truncate text-[11px] text-slate-400">
                            {{ $deploymentMode === 'self_hosted' ? __('ui.deployment_self_hosted') : __('ui.deployment_saas') }}
                        </p>
                    </div>
                </a>

                <button
                    type="button"
                    x-on:click="collapsed = !collapsed"
                    class="hidden rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white lg:inline-flex"
                    title="Toggle Sidebar"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                </button>
                <button
                    type="button"
                    x-on:click="mobileOpen = false"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Navigation Modules --}}
            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5 text-sm">
                @if ($ctx->id() !== null)
                    <div>
                        <p x-show="!collapsed" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.nav_core') }}</p>
                        <ul class="space-y-1">
                            @if ($ctx->hasPermission('dashboard.view'))
                                <li>
                                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('dashboard') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_dashboard') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('branches.view'))
                                <li>
                                    <a href="{{ route('organization.branches') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('organization.branches') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_organization') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('team.view'))
                                <li>
                                    <a href="{{ route('team.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('team.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_team') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasAnyPermission(['inventory.view', 'sales.view']))
                                <li>
                                    <a href="{{ route('products.index') }}" data-testid="nav-products" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('products.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_products') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('inventory.view'))
                                <li>
                                    <a href="{{ route('inventory.index') }}" data-testid="nav-inventory" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('inventory.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_inventory') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('sales.view'))
                                <li>
                                    <a href="{{ route('orders.index') }}" data-testid="nav-orders" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('orders.index') || request()->routeIs('orders.invoice') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_sales') }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('customers.index') }}" data-testid="nav-customers" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('customers.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_customers') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasAnyPermission(['reports.view', 'sales.view']))
                                <li>
                                    <a href="{{ route('financials.index') }}" data-testid="nav-financials" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('financials.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_financials') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('integrations.view'))
                                <li>
                                    <a href="{{ route('integrations.index') }}" data-testid="nav-integrations" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('integrations.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_integrations') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasAnyPermission(['commands.request', 'commands.approve', 'audit.view']))
                                <li>
                                    <a href="{{ route('commands.index') }}" data-testid="nav-commands" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('commands.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_commands') }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($ctx->hasPermission('reports.view'))
                                <li>
                                    <a href="{{ route('reports.index') }}" data-testid="nav-reports" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('reports.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span x-show="!collapsed" class="truncate">{{ __('ui.nav_reports') }}</span>
                                    </a>
                                </li>
                            @endif

                            <li>
                                <a href="{{ route('alerts.index') }}" data-testid="nav-alerts" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('alerts.index') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-900 hover:text-white' }}">
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    <span x-show="!collapsed" class="truncate">{{ __('ui.nav_alerts') }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif

                @if ($user?->is_platform_admin)
                    <div>
                        <p x-show="!collapsed" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-amber-400/80">{{ __('ui.nav_platform_admin') }}</p>
                        <ul class="space-y-1">
                            <li>
                                <a href="{{ route('platform.tenants') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition {{ request()->routeIs('platform.tenants') ? 'bg-amber-600 text-white' : 'text-amber-200 hover:bg-slate-900 hover:text-white' }}">
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    <span x-show="!collapsed" class="truncate">{{ __('ui.nav_tenants') }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif
            </nav>

            {{-- Footer Status --}}
            <div class="border-t border-slate-800 p-3 text-xs">
                <a href="{{ route('system.status') }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-slate-400 hover:bg-slate-900 hover:text-slate-200">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>
                    <span x-show="!collapsed">{{ __('ui.nav_system_status') }}</span>
                </a>
            </div>
        </aside>

        {{-- Main Column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Top Contextual Header --}}
            <header class="sticky top-0 z-30 flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 py-2.5 backdrop-blur-xs dark:border-slate-800 dark:bg-slate-900/95 sm:px-6">
                <div class="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        x-on:click="mobileOpen = true"
                        data-testid="mobile-menu-btn"
                        class="inline-flex rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden"
                        aria-label="Open Navigation"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    {{-- Active Tenant Switcher --}}
                    @if ($userMemberships->count() > 0 && $deploymentMode === 'saas')
                        <form action="{{ route('context.switch-tenant') }}" method="POST" class="flex items-center">
                            @csrf
                            <label for="tenant_switcher" class="sr-only">{{ __('ui.active_tenant') }}</label>
                            <select
                                id="tenant_switcher"
                                name="tenant_id"
                                data-testid="tenant-switcher"
                                onchange="this.form.submit()"
                                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            >
                                @foreach ($userMemberships as $m)
                                    <option value="{{ $m->tenant_id }}" @selected($activeTenant && (int) $activeTenant->id === (int) $m->tenant_id)>
                                        {{ $m->tenant->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @elseif ($activeTenant)
                        <span data-testid="active-tenant-label" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            {{ $activeTenant->name }}
                        </span>
                    @endif

                    {{-- Scoped Branch Selector --}}
                    @if ($ctx->id() !== null && $headerBranches->count() > 0)
                        <form action="{{ route('context.switch-branch') }}" method="POST" class="flex items-center">
                            @csrf
                            <label for="branch_switcher" class="sr-only">{{ __('ui.active_branch') }}</label>
                            <select
                                id="branch_switcher"
                                name="branch_id"
                                data-testid="branch-switcher"
                                onchange="this.form.submit()"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                @if ($ctx->hasFullBranchAccess() || $headerBranches->count() > 1)
                                    <option value="all" @selected($ctx->activeBranchId() === null)>{{ __('ui.all_branches') }}</option>
                                @endif
                                @foreach ($headerBranches as $b)
                                    <option value="{{ $b->id }}" @selected((int) $ctx->activeBranchId() === (int) $b->id)>
                                        {{ $b->name }} ({{ $b->code }})
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif

                    {{-- Honest Sync Status Pill --}}
                    <x-ui.status-badge :variant="$syncHeaderStatus" data-testid="sync-status-pill" class="hidden sm:inline-flex">
                        {{ $syncHeaderLabel }}
                    </x-ui.status-badge>
                </div>

                {{-- Right Controls --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if ($user && $ctx->id() !== null)
                        @livewire('header-notification-bell')
                    @endif

                    {{-- Theme Toggle --}}
                    <form action="{{ route('preferences.theme') }}" method="POST">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $theme === 'dark' ? 'light' : 'dark' }}">
                        <button
                            type="submit"
                            data-testid="theme-toggle"
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            {{ $theme === 'dark' ? __('ui.switch_theme_light') : __('ui.switch_theme_dark') }}
                        </button>
                    </form>

                    {{-- Locale Toggle --}}
                    <a
                        href="{{ route('preferences.locale', ['locale' => $locale === 'ar' ? 'en' : 'ar']) }}"
                        data-testid="locale-toggle"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        {{ __('ui.locale_switch_label') }}
                    </a>

                    {{-- User & Logout --}}
                    @if ($user)
                        <div class="hidden items-center gap-2 border-s border-slate-200 ps-2 dark:border-slate-700 md:flex">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $user->name }}</span>
                        </div>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                data-testid="logout-btn"
                                class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/50"
                            >
                                {{ __('ui.sign_out') }}
                            </button>
                        </form>
                    @endif
                </div>
            </header>

            {{-- Page Content --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-7xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <x-ui.toast />
    @livewireScripts
</body>
</html>
