<div class="space-y-6">
    @php
        $overall = $report['status'] ?? 'degraded';
        $platformDb = $report['checks']['rbd_platform'] ?? [];
        $mockPosDb = $report['checks']['mock_pos_db'] ?? [];
        $reverb = $report['checks']['reverb'] ?? [];
        $runtime = $report['runtime'] ?? [];
        $isArabic = ($runtime['locale'] ?? app()->getLocale()) === 'ar';
    @endphp

    <header class="flex flex-col gap-4 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-white">{{ __('system.title') }}</h1>
                <span
                    data-testid="overall-status-badge"
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $overall === 'ok' ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/40' : 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/40' }}"
                >
                    <span class="h-2 w-2 rounded-full {{ $overall === 'ok' ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                    {{ $overall === 'ok' ? __('system.status_ok') : __('system.status_degraded') }}
                </span>
            </div>
            <p class="text-sm text-slate-400">{{ __('system.subtitle') }}</p>
            <p class="text-xs text-slate-500">
                <span data-testid="direction-badge">{{ __('system.direction_badge') }}</span>
                <span class="mx-2">•</span>
                <span>{{ __('system.last_checked') }}: <time data-testid="checked-at">{{ $report['timestamp'] ?? '' }}</time></span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a
                href="{{ route('system.status', ['lang' => $isArabic ? 'en' : 'ar']) }}"
                data-testid="locale-toggle"
                class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-sky-500 hover:text-white"
            >
                {{ $isArabic ? __('system.switch_to_en') : __('system.switch_to_ar') }}
            </a>

            <button
                type="button"
                wire:click="refreshStatus"
                wire:loading.attr="disabled"
                data-testid="refresh-status-btn"
                class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="refreshStatus">{{ __('system.refresh_btn') }}</span>
                <span wire:loading wire:target="refreshStatus">{{ __('system.refreshing') }}</span>
            </button>
        </div>
    </header>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        {{-- Runtime Card --}}
        <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-sky-400">{{ __('system.runtime_card') }}</h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.php_version') }}</dt>
                    <dd data-testid="php-version" class="font-mono font-semibold text-white">{{ $runtime['php'] ?? '' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.laravel_version') }}</dt>
                    <dd data-testid="laravel-version" class="font-mono font-semibold text-white">{{ $runtime['laravel'] ?? '' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.db_engine') }}</dt>
                    <dd data-testid="db-engine" class="font-mono font-semibold text-white">{{ $platformDb['engine'] ?? '' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.deployment_mode') }}</dt>
                    <dd data-testid="deployment-mode" class="rounded bg-slate-800 px-2 py-0.5 font-mono text-xs text-sky-300">{{ $runtime['deployment_mode'] ?? '' }}</dd>
                </div>
            </dl>
        </article>

        {{-- Platform DB Card --}}
        <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-200">{{ __('system.platform_db_card') }}</h2>
                <span
                    data-testid="platform-db-status"
                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($platformDb['status'] ?? '') === 'ok' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}"
                >
                    {{ ($platformDb['status'] ?? '') === 'ok' ? __('system.status_ok') : __('system.status_error') }}
                </span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">Database</dt>
                    <dd class="font-mono text-white">{{ $platformDb['database'] ?? 'rbd_platform' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.latency') }}</dt>
                    <dd class="font-mono text-slate-200">{{ isset($platformDb['latency_ms']) ? $platformDb['latency_ms'].' ms' : '—' }}</dd>
                </div>
            </dl>
            @if (!empty($platformDb['error']))
                <p class="mt-3 rounded-lg bg-rose-950/60 p-2 text-xs text-rose-300">{{ $platformDb['error'] }}</p>
            @endif
        </article>

        {{-- Reverb Card --}}
        <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-200">{{ __('system.reverb_card') }}</h2>
                <span
                    data-testid="reverb-status"
                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($reverb['status'] ?? '') === 'ok' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}"
                >
                    {{ ($reverb['status'] ?? '') === 'ok' ? __('system.status_ok') : __('system.status_unreachable') }}
                </span>
            </div>
            <dl class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('system.endpoint') }}</dt>
                    <dd class="font-mono text-white">{{ ($reverb['scheme'] ?? 'http').'://'.($reverb['host'] ?? '127.0.0.1').':'.($reverb['port'] ?? 8080) }}</dd>
                </div>
            </dl>
            @if (!empty($reverb['error']))
                <p class="mt-3 rounded-lg bg-rose-950/60 p-2 text-xs text-rose-300">{{ $reverb['error'] }}</p>
            @endif
        </article>
    </section>

    {{-- Mock POS DB Live Row Counts --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-white">{{ __('system.mock_pos_card') }}</h2>
            <span
                data-testid="mock-pos-status"
                class="rounded-full px-3 py-1 text-xs font-semibold {{ ($mockPosDb['status'] ?? '') === 'ok' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}"
            >
                {{ ($mockPosDb['status'] ?? '') === 'ok' ? __('system.status_ok') : __('system.status_error') }}
            </span>
        </div>

        @if (!empty($mockPosDb['error']))
            <p class="mb-4 rounded-xl bg-rose-950/60 p-3 text-sm text-rose-300">{{ $mockPosDb['error'] }}</p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400">
                        <th class="px-4 py-3 text-start font-medium">{{ __('system.table_name') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('system.row_count') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    @foreach (($mockPosDb['counts'] ?? []) as $table => $count)
                        <tr data-testid="mock-row-{{ $table }}" class="hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-sky-300">{{ $table }}</td>
                            <td data-testid="mock-count-{{ $table }}" class="px-4 py-3 text-end font-mono font-bold text-white">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <a href="{{ route('api.v1.health') }}" target="_blank" class="text-xs font-medium text-sky-400 hover:text-sky-300">
                {{ __('system.api_health_link') }} &rarr;
            </a>
        </div>
    </section>
</div>
