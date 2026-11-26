@props([
    'label',
    'value',
    'hint' => null,
    'accent' => 'sky',
])

@php
    $accentBorder = match ($accent) {
        'emerald' => 'border-s-emerald-500',
        'amber' => 'border-s-amber-500',
        'rose' => 'border-s-rose-500',
        default => 'border-s-sky-600 dark:border-s-sky-400',
    };
@endphp

<article {{ $attributes->merge(['class' => "rounded-xl border border-slate-200 dark:border-slate-800 border-s-4 {$accentBorder} bg-white dark:bg-slate-900 p-5 shadow-xs transition-colors"]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</article>
