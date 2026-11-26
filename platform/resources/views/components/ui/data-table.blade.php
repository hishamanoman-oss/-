@props([
    'empty' => false,
    'emptyMessage' => null,
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs']) }}>
    @isset($toolbar)
        <div class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 px-4 py-3">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="relative overflow-x-auto">
        <div wire:loading.flex class="absolute inset-0 z-10 hidden items-center justify-center bg-white/60 dark:bg-slate-950/60 backdrop-blur-[1px]">
            <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-slate-800">...</span>
        </div>

        @if ($empty)
            <div class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ $emptyMessage ?? __('ui.no_branches_found') }}
            </div>
        @else
            <table class="w-full text-start text-sm">
                @isset($head)
                    <thead class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                        {{ $head }}
                    </thead>
                @endisset
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-800 dark:text-slate-200">
                    {{ $slot }}
                </tbody>
            </table>
        @endif
    </div>

    @isset($pagination)
        <div class="border-t border-slate-200 dark:border-slate-800 px-4 py-3">
            {{ $pagination }}
        </div>
    @endisset
</div>
