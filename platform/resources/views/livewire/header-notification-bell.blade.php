<div class="relative" x-data="{}" @click.outside="$wire.set('open', false)">
    <button
        type="button"
        wire:click="toggleDropdown"
        data-testid="header-bell-btn"
        aria-label="{{ __('ui.notifications_bell') }}"
        class="relative inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($unreadCount > 0)
            <span
                data-testid="header-bell-badge"
                class="absolute -end-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
            >
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div
            data-testid="header-bell-dropdown"
            class="absolute end-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="mb-2 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ __('ui.notifications_bell') }} ({{ $unreadCount }})</span>
                <a href="{{ route('alerts.index') }}" data-testid="bell-view-all-link" class="text-xs font-semibold text-sky-700 hover:underline dark:text-sky-400">
                    {{ __('ui.view_all_alerts') }}
                </a>
            </div>

            @if ($recent->isEmpty())
                <p class="py-4 text-center text-xs text-slate-500">{{ __('ui.no_notifications') }}</p>
            @else
                <ul class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach ($recent as $item)
                        <li class="rounded-xl border border-slate-100 p-2.5 text-xs dark:border-slate-800 {{ $item->read_at ? 'opacity-70' : 'bg-sky-50/40 dark:bg-sky-950/20' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $item->title }}</p>
                                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $item->severity === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : ($item->severity === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300') }}">
                                    {{ $item->severity }}
                                </span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-[11px] text-slate-600 dark:text-slate-300">{{ $item->body }}</p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                @if ($item->action_url)
                                    <a href="{{ url($item->action_url) }}" class="text-[11px] font-medium text-sky-700 hover:underline dark:text-sky-400">{{ __('ui.open') }}</a>
                                @endif
                                @if ($item->read_at === null)
                                    <button type="button" wire:click="markRead({{ $item->id }})" class="text-[11px] font-medium text-emerald-700 hover:underline dark:text-emerald-400">
                                        {{ __('ui.mark_read') }}
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
