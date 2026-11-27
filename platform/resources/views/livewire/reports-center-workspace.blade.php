<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.reports_center_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.reports_center_subtitle') }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
        <button
            type="button"
            wire:click="$set('activeTab', 'export')"
            data-testid="tab-reports-export"
            class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $activeTab === 'export' ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"
        >
            {{ __('ui.reports_export_tab') }}
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'targets')"
            data-testid="tab-reports-targets"
            class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $activeTab === 'targets' ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"
        >
            {{ __('ui.reports_targets_tab') }}
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'scheduled')"
            data-testid="tab-reports-scheduled"
            class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $activeTab === 'scheduled' ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"
        >
            {{ __('ui.reports_scheduled_tab') }}
        </button>
    </div>

    @if ($activeTab === 'export')
        <div wire:key="tab-panel-export" data-testid="tab-panel-export" class="space-y-6">
            {{-- Filter & Export Controls --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.report_type') }}</label>
                        <select wire:model.live="reportType" data-testid="report-type-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                            <option value="sales_summary">{{ __('ui.rep_sales_summary') }}</option>
                            <option value="branch_comparison">{{ __('ui.rep_branch_comparison') }}</option>
                            <option value="inventory_health">{{ __('ui.rep_inventory_health') }}</option>
                            <option value="customer_ltv">{{ __('ui.rep_customer_ltv') }}</option>
                            <option value="financial_statement">{{ __('ui.rep_financial_statement') }}</option>
                            <option value="audit_activity">{{ __('ui.rep_audit_activity') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.period_label') }}</label>
                        <select wire:model.live="period" data-testid="report-period-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                            <option value="today">{{ __('ui.period_today') }}</option>
                            <option value="7d">{{ __('ui.period_7d') }}</option>
                            <option value="30d">{{ __('ui.period_30d') }}</option>
                            <option value="this_month">{{ __('ui.period_this_month') }}</option>
                            <option value="custom">{{ __('ui.period_custom') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.branch_scope') }}</label>
                        <select wire:model.live="selectedBranchId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                            <option value="">{{ __('ui.all_branches') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap items-end gap-2">
                        <a
                            href="{{ route('reports.export', array_merge($exportQueryBase, ['format' => 'csv'])) }}"
                            data-testid="export-csv-btn"
                            class="inline-flex items-center rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600"
                        >
                            CSV
                        </a>
                        <a
                            href="{{ route('reports.export', array_merge($exportQueryBase, ['format' => 'xlsx'])) }}"
                            data-testid="export-xlsx-btn"
                            class="inline-flex items-center rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-600"
                        >
                            Excel (.xlsx)
                        </a>
                        <a
                            href="{{ route('reports.export', array_merge($exportQueryBase, ['format' => 'pdf'])) }}"
                            data-testid="export-pdf-btn"
                            class="inline-flex items-center rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600"
                        >
                            PDF
                        </a>
                    </div>
                </div>
            </div>

            {{-- Chart.js Preview --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $preview['title'] }}</h2>
                    <span class="font-mono text-xs text-slate-500">{{ $preview['subtitle'] }}</span>
                </div>
                <div class="relative h-64 w-full" wire:ignore.self>
                    <canvas
                        data-rbd-chart="report-preview"
                        data-testid="report-preview-chart"
                        data-chart-config='@json($reportChartSpec)'
                    ></canvas>
                </div>
            </div>

            {{-- Live Table Preview --}}
            <x-ui.data-table :empty="count($preview['rows']) === 0" :empty-message="__('ui.no_records_found')">
                <x-slot:head>
                    <tr>
                        @foreach ($preview['headers'] as $h)
                            <th class="px-4 py-3 text-start">{{ $h }}</th>
                        @endforeach
                    </tr>
                </x-slot:head>
                @foreach ($preview['rows'] as $r)
                    <tr>
                        @foreach ($r as $cell)
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if (count($preview['notes']) > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                    @foreach ($preview['notes'] as $note)
                        <p>{{ $note }}</p>
                    @endforeach
                </div>
            @endif
        </div>

    @elseif ($activeTab === 'targets')
        <div wire:key="tab-panel-targets" data-testid="tab-panel-targets" class="grid gap-6 {{ $canManageTargets ? 'lg:grid-cols-3' : 'lg:grid-cols-1' }}">
            @if ($canManageTargets)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('ui.create_performance_target') }}</h2>
                <form wire:submit="createTarget" class="mt-4 space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.metric_type') }}</label>
                        <select wire:model="targetMetric" data-testid="target-metric-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="net_sales">{{ __('ui.net_sales') }}</option>
                            <option value="paid_revenue">{{ __('ui.paid_revenue') }}</option>
                            <option value="orders_count">{{ __('ui.orders_count') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.period_type') }}</label>
                        <select wire:model="targetPeriod" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="daily">daily</option>
                            <option value="weekly">weekly</option>
                            <option value="monthly">monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.branch_scope') }}</label>
                        <select wire:model="targetBranchId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="">{{ __('ui.all_branches') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.target_value') }}</label>
                        <input type="text" wire:model="targetValue" data-testid="target-value-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
                        @error('targetValue') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.from') }}</label>
                            <input type="date" wire:model="targetStart" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.to') }}</label>
                            <input type="date" wire:model="targetEnd" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                        </div>
                    </div>
                    <button type="submit" data-testid="create-target-btn" class="w-full rounded-xl bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-600">
                        {{ __('ui.save_target') }}
                    </button>
                </form>
            </div>
            @endif

            <div class="space-y-3 {{ $canManageTargets ? 'lg:col-span-2' : '' }}">
                @forelse ($targets as $t)
                    <div wire:key="target-card-{{ $t['id'] }}" data-testid="target-card-{{ $t['id'] }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $t['metric_type'] }} — {{ $t['branch_name'] }}</p>
                                <p class="font-mono text-[11px] text-slate-500">{{ $t['period_type'] }} ({{ $t['period_start'] }} .. {{ $t['period_end'] }})</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-sky-700 dark:text-sky-300">{{ number_format((float) $t['attainment_percent'], 1) }}%</span>
                                @if ($canManageTargets)
                                <button type="button" wire:click="deleteTarget({{ $t['id'] }})" class="rounded-lg border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300">
                                    {{ __('ui.delete') }}
                                </button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full {{ $t['status'] === 'exceeded' ? 'bg-emerald-500' : ($t['status'] === 'on_track' ? 'bg-sky-600' : 'bg-amber-500') }}" style="width: {{ number_format((float) $t['bar_width_percent'], 2, '.', '') }}%"></div>
                        </div>
                        <div class="mt-2 flex flex-wrap justify-between font-mono text-xs text-slate-600 dark:text-slate-300">
                            <span>{{ __('ui.actual') }}: {{ number_format((float) $t['actual_value'], 2) }} {{ $t['metric_type'] !== 'orders_count' ? $t['currency_code'] : '' }}</span>
                            <span>{{ __('ui.target') }}: {{ number_format((float) $t['target_value'], 2) }} {{ $t['metric_type'] !== 'orders_count' ? $t['currency_code'] : '' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">
                        {{ __('ui.no_targets_found') }}
                    </div>
                @endforelse
            </div>
        </div>

    @else
        <div wire:key="tab-panel-scheduled" data-testid="tab-panel-scheduled" class="grid gap-6 {{ $canManageScheduled ? 'lg:grid-cols-3' : 'lg:grid-cols-1' }}">
            @if ($canManageScheduled)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('ui.create_scheduled_report') }}</h2>
                <form wire:submit="createScheduledReport" class="mt-4 space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.report_name') }}</label>
                        <input type="text" wire:model="schedName" data-testid="sched-name-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @error('schedName') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.report_type') }}</label>
                        <select wire:model="schedType" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="sales_summary">sales_summary</option>
                            <option value="branch_comparison">branch_comparison</option>
                            <option value="inventory_health">inventory_health</option>
                            <option value="customer_ltv">customer_ltv</option>
                            <option value="financial_statement">financial_statement</option>
                            <option value="audit_activity">audit_activity</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.frequency') }}</label>
                            <select wire:model="schedFrequency" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                                <option value="daily">daily</option>
                                <option value="weekly">weekly</option>
                                <option value="monthly">monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.format') }}</label>
                            <select wire:model="schedFormat" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                                <option value="csv">CSV</option>
                                <option value="xlsx">Excel (.xlsx)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.recipients_emails') }}</label>
                        <input type="text" wire:model="schedRecipients" data-testid="sched-recipients-input" placeholder="owner@demo.local, cfo@demo.local" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                        @error('schedRecipients') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" data-testid="create-scheduled-btn" class="w-full rounded-xl bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-600">
                        {{ __('ui.save_schedule') }}
                    </button>
                </form>
            </div>
            @endif

            <div class="{{ $canManageScheduled ? 'lg:col-span-2' : '' }}">
                <x-ui.data-table :empty="$scheduledReports->isEmpty()" :empty-message="__('ui.no_scheduled_reports')">
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('ui.report_name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.frequency') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.format') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($scheduledReports as $sr)
                        <tr wire:key="scheduled-row-{{ $sr->id }}" data-testid="scheduled-row-{{ $sr->id }}">
                            <td class="px-4 py-3 text-xs">
                                <span class="font-semibold">{{ $sr->name }}</span>
                                <span class="block font-mono text-[11px] text-slate-500">{{ $sr->report_type }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $sr->frequency }}</td>
                            <td class="px-4 py-3 font-mono text-xs uppercase">{{ $sr->format }}</td>
                            <td class="px-4 py-3">
                                <x-ui.status-badge :variant="$sr->is_active ? 'live' : 'disconnected'" :dot="false">
                                    {{ $sr->is_active ? 'active' : 'paused' }}
                                </x-ui.status-badge>
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if ($canManageScheduled)
                                <div class="inline-flex flex-wrap gap-1.5">
                                    <button type="button" wire:click="runScheduledNow({{ $sr->id }})" class="rounded-lg bg-sky-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-sky-600">
                                        {{ __('ui.run_now') }}
                                    </button>
                                    <button type="button" wire:click="toggleScheduled({{ $sr->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium hover:bg-slate-50 dark:border-slate-700">
                                        {{ $sr->is_active ? __('ui.pause') : __('ui.activate') }}
                                    </button>
                                </div>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </div>
        </div>
    @endif
</div>
