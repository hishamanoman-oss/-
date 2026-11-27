<x-ui.app-shell :title="__('ui.invoice_title').' '.$order->order_number">
    <div class="mx-auto max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900 print:border-0 print:shadow-none">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $tenant?->name }}</p>
                <h1 class="mt-1 font-mono text-2xl font-bold text-slate-900 dark:text-white" data-testid="invoice-order-number">
                    {{ $order->order_number }}
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $order->branch?->name }} ({{ $order->branch?->code }}) • {{ $order->placed_at?->format('Y-m-d H:i') }}
                    @if ($externalRef)
                        • POS #{{ $externalRef }}
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2 print:hidden">
                <a href="{{ route('orders.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    {{ __('ui.back_to_orders') }}
                </a>
                <button type="button" onclick="window.print()" data-testid="print-invoice-btn" class="rounded-lg bg-sky-700 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-600">
                    {{ __('ui.print_invoice') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                <p class="font-bold text-slate-700 dark:text-slate-200">{{ __('ui.customer') }}</p>
                <p class="mt-1 text-sm font-semibold">{{ $order->customer?->name ?? '—' }}</p>
                <p class="font-mono text-slate-500">{{ $order->customer?->code ?? '' }} {{ $order->customer?->phone ?? '' }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60">
                <p class="font-bold text-slate-700 dark:text-slate-200">{{ __('ui.order_status') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-ui.status-badge variant="info" :dot="false">{{ $order->order_status }}</x-ui.status-badge>
                    <x-ui.status-badge variant="active" :dot="false">{{ $order->payment_status }}</x-ui.status-badge>
                    <x-ui.status-badge variant="neutral" :dot="false">{{ $order->fulfillment_status }}</x-ui.status-badge>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table class="w-full text-start text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/70">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.product_sku') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.quantity') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.unit_price') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.line_total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach ($order->items as $item)
                        <tr data-testid="invoice-item-{{ $item->sku_snapshot }}">
                            <td class="px-4 py-3">
                                <span class="font-medium">{{ $item->product_name_snapshot }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ $item->sku_snapshot }}</span>
                            </td>
                            <td class="px-4 py-3 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $item->quantity, 2) }}</td>
                            <td class="px-4 py-3 text-end font-mono">{{ $decimalHelper::formatDisplay((string) $item->unit_price_snapshot, 2) }}</td>
                            <td class="px-4 py-3 text-end font-mono font-semibold">{{ $decimalHelper::formatDisplay((string) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 font-mono text-xs dark:bg-slate-800/60 sm:grid-cols-3">
            <div>{{ __('ui.gross_sales') }}: {{ $decimalHelper::formatDisplay((string) $order->gross_sales, 2) }} {{ $order->currency_code }}</div>
            <div>{{ __('ui.discounts') }}: {{ $decimalHelper::formatDisplay((string) $order->discount_total, 2) }} {{ $order->currency_code }}</div>
            <div>{{ __('ui.tax_total') }}: {{ $decimalHelper::formatDisplay((string) $order->tax_total, 2) }} {{ $order->currency_code }}</div>
            <div class="font-bold">{{ __('ui.net_sales') }}: {{ $decimalHelper::formatDisplay((string) $order->net_sales, 2) }} {{ $order->currency_code }}</div>
            <div class="text-emerald-600">{{ __('ui.paid_total') }}: {{ $decimalHelper::formatDisplay((string) $order->paid_total, 2) }} {{ $order->currency_code }}</div>
            <div class="text-amber-600">{{ __('ui.outstanding_total') }}: {{ $decimalHelper::formatDisplay((string) $order->outstanding_total, 2) }} {{ $order->currency_code }}</div>
        </div>

        @if ($order->payments->isNotEmpty() || $order->refunds->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <p class="mb-2 font-bold">{{ __('ui.payments_history') }}</p>
                    @forelse ($order->payments as $p)
                        <p class="font-mono">{{ $p->payment_reference }} ({{ $p->method }}): {{ $decimalHelper::formatDisplay((string) $p->amount, 2) }}</p>
                    @empty
                        <p class="text-slate-400">—</p>
                    @endforelse
                </div>
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <p class="mb-2 font-bold">{{ __('ui.refunds_history') }}</p>
                    @forelse ($order->refunds as $r)
                        <p class="font-mono text-rose-600">{{ $decimalHelper::formatDisplay((string) $r->amount, 2) }} — {{ $r->reason }}</p>
                    @empty
                        <p class="text-slate-400">—</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-ui.app-shell>
