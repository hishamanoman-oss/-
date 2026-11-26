@props([
    'show' => false,
    'title' => '',
    'closeAction' => '',
])

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
                @if ($closeAction)
                    <button type="button" wire:click="{{ $closeAction }}" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
            <div class="p-6">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
