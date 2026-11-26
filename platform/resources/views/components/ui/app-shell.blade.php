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
    if ($ctx->id() !== null) {
        $branchQuery = \App\Domain\Organizations\Models\Branch::query()->orderBy('id');
        if (is_array($accessibleBranchIds)) {
            $branchQuery->whereIn('id', $accessibleBranchIds === [] ? [-1] : $accessibleBranchIds);
        }
        $headerBranches = $branchQuery->get();
    }
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="{{ $theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ __('ui.app_name') }}</title>
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
                        </ul>
                    </div>

                    {{-- Upcoming Role-Permitted Operational Modules (explicitly marked as upcoming phase status) --}}
                    @php
                        $upcomingLinks = array_filter([
                            $ctx->hasPermission('sales.view') ? ['label' => __('ui.nav_sales'), 'phase' => 'P04/06'] : null,
                            $ctx->hasPermission('inventory.view') ? ['label' => __('ui.nav_inventory'), 'phase' => 'P04/06'] : null,
                            $ctx->hasPermission('integrations.view') ? ['label' => __('ui.nav_integrations'), 'phase' => 'P05A'] : null,
                            ($ctx->hasPermission('commands.request') || $ctx->hasPermission('commands.approve')) ? ['label' => __('ui.nav_commands'), 'phase' => 'P07'] : null,
                            $ctx->hasPermission('reports.view') ? ['label' => __('ui.nav_reports'), 'phase' => 'P04/06'] : null,
                        ]);
                    @endphp
                    @if (count($upcomingLinks) > 0)
                        <div x-show="!collapsed">
                            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.nav_operations') }}</p>
                            <ul class="space-y-1">
                                @foreach ($upcomingLinks as $item)
                                    <li class="flex items-center justify-between rounded-lg px-3 py-1.5 text-xs text-slate-500">
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[10px] text-slate-400">{{ $item['phase'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
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
                    <x-ui.status-badge variant="disconnected" data-testid="sync-status-pill" class="hidden sm:inline-flex">
                        {{ __('ui.sync_disconnected') }}
                    </x-ui.status-badge>
                </div>

                {{-- Right Controls --}}
                <div class="flex items-center gap-2">
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
