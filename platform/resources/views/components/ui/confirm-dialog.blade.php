@props([
    'show' => false,
    'title' => '',
    'message' => '',
    'confirmAction' => '',
    'cancelAction' => '',
])

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-2xl">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $message }}</p>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" wire:click="{{ $cancelAction }}" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ __('ui.cancel') }}
                </button>
                <button type="button" wire:click="{{ $confirmAction }}" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                    {{ __('ui.save') }}
                </button>
            </div>
        </div>
    </div>
@endif
