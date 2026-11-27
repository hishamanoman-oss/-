import './echo';

const SYNC_PILL_STYLES = {
    live: {
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        dot: 'bg-emerald-500',
    },
    delayed: {
        badge: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        dot: 'bg-amber-500',
    },
    syncing: {
        badge: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
        dot: 'bg-sky-500 animate-pulse',
    },
    error: {
        badge: 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        dot: 'bg-rose-500',
    },
    disconnected: {
        badge: 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        dot: 'bg-slate-400',
    },
};

function updateHeaderSyncPill(freshnessStatus) {
    const pill = document.querySelector('[data-testid="sync-status-pill"]');
    if (!pill || !freshnessStatus) return;

    const cfg = window.__rbdSyncConfig || {};
    const label = cfg.labels?.[freshnessStatus] || freshnessStatus;
    const style = SYNC_PILL_STYLES[freshnessStatus] || SYNC_PILL_STYLES.disconnected;

    pill.className = `inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset hidden sm:inline-flex ${style.badge}`;
    pill.innerHTML = `<span class="h-1.5 w-1.5 rounded-full ${style.dot}"></span>`;
    pill.appendChild(document.createTextNode(label));
}

function initTenantRealtimeSubscription() {
    const tenantMeta = document.querySelector('meta[name="active-tenant-id"]');
    const tenantId = tenantMeta?.getAttribute('content')?.trim();
    if (!tenantId || !window.Echo) {
        return;
    }

    const channelName = `tenant.${tenantId}`;
    window.Echo.private(channelName)
        .listen('.sync.batch.completed', (payload) => {
            if (payload?.freshnessStatus) {
                updateHeaderSyncPill(payload.freshnessStatus);
            }

            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('sync-realtime-refresh', { payload });
            }

            if ((payload?.recordsUpserted ?? 0) > 0) {
                const tpl = window.__rbdSyncConfig?.toastTemplate || 'Realtime sync updated (:count records)';
                const msg = tpl.replace(':count', String(payload.recordsUpserted));
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, type: 'success' } }));
            }
        })
        .listen('.sync.freshness.updated', (payload) => {
            if (payload?.freshnessStatus) {
                updateHeaderSyncPill(payload.freshnessStatus);
            }

            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('sync-freshness-refresh', { payload });
            }
        })
        .listen('.command.updated', (payload) => {
            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('command-realtime-refresh', { payload });
                window.Livewire.dispatch('sync-realtime-refresh', { payload });
            }
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTenantRealtimeSubscription);
} else {
    initTenantRealtimeSubscription();
}
