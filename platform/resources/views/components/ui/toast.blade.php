<div
    x-data="{ show: false, message: '', type: 'success', timer: null }"
    x-on:toast.window="
        clearTimeout(timer);
        message = $event.detail.message || '';
        type = $event.detail.type || 'success';
        show = true;
        timer = setTimeout(() => show = false, 3500);
    "
    x-show="show"
    x-cloak
    x-transition
    data-testid="toast-banner"
    class="fixed bottom-5 end-5 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-lg"
    :class="type === 'error'
        ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-700 dark:bg-rose-950 dark:text-rose-200'
        : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200'"
>
    <span x-text="message"></span>
    <button type="button" x-on:click="show = false" class="opacity-70 hover:opacity-100">&times;</button>
</div>
