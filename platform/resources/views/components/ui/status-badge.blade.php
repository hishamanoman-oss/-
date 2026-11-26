@props([
    'variant' => 'neutral',
    'dot' => true,
])

@php
    $styles = [
        'live' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        'delayed' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'syncing' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
        'error' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        'inactive' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        'suspended' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        'disconnected' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
    ];

    $dots = [
        'live' => 'bg-emerald-500',
        'active' => 'bg-emerald-500',
        'delayed' => 'bg-amber-500',
        'warning' => 'bg-amber-500',
        'syncing' => 'bg-sky-500 animate-pulse',
        'info' => 'bg-sky-500',
        'error' => 'bg-rose-500',
        'inactive' => 'bg-rose-500',
        'suspended' => 'bg-rose-500',
        'disconnected' => 'bg-slate-400',
        'neutral' => 'bg-slate-400',
    ];

    $cls = $styles[$variant] ?? $styles['neutral'];
    $dotCls = $dots[$variant] ?? $dots['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {$cls}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotCls }}"></span>
    @endif
    {{ $slot }}
</span>
