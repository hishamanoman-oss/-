<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.alerts_center_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.alerts_center_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canManageRules)
                <button
                    type="button"
                    wire:click="evaluateNow"
                    data-testid="evaluate-rules-btn"
                    class="rounded-xl bg-sky-700 px-3.5 py-2 text-xs font-semibold text-white hover:bg-sky-600"
                >
                    {{ __('ui.evaluate_rules_now') }}
                </button>
            @endif
            <button
                type="button"
                wire:click="markAllRead"
                data-testid="mark-all-read-btn"
                class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
                {{ __('ui.mark_all_read') }}
            </button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
        <button
            type="button"
            wire:click="$set('activeTab', 'inbox')"
            data-testid="tab-alerts-inbox"
            class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $activeTab === 'inbox' ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"
        >
            {{ __('ui.alerts_inbox_tab') }}
        </button>
        @if ($canManageRules)
            <button
                type="button"
                wire:click="$set('activeTab', 'rules')"
                data-testid="tab-alerts-rules"
                class="rounded-lg px-3.5 py-2 text-xs font-semibold {{ $activeTab === 'rules' ? 'bg-sky-700 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}"
            >
                {{ __('ui.alerts_rules_tab') }}
            </button>
        @endif
    </div>

    @if ($activeTab === 'inbox')
        <div wire:key="alerts-tab-inbox" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select wire:model.live="inboxFilter" data-testid="alerts-read-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                    <option value="all">{{ __('ui.all_notifications') }}</option>
                    <option value="unread">{{ __('ui.unread_only') }}</option>
                </select>
                <select wire:model.live="severityFilter" data-testid="alerts-severity-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">
                    <option value="all">{{ __('ui.all_severities') }}</option>
                    <option value="critical">critical</option>
                    <option value="warning">warning</option>
                    <option value="info">info</option>
                </select>
            </div>

            @if ($notifications->isEmpty())
                <p class="py-8 text-center text-sm text-slate-500">{{ __('ui.no_notifications') }}</p>
            @else
                <ul class="space-y-3">
                    @foreach ($notifications as $item)
                        <li wire:key="alert-item-{{ $item->id }}" data-testid="alert-item-{{ $item->id }}" class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 {{ $item->read_at ? 'opacity-75' : 'bg-sky-50/40 dark:bg-sky-950/20' }}">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase {{ $item->severity === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : ($item->severity === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300') }}">
                                        {{ $item->severity }}
                                    </span>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $item->title }}</h3>
                                    @if ($item->read_at === null)
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300">{{ $item->body }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $item->created_at?->format('Y-m-d H:i') }} @if ($item->branch) • {{ $item->branch->name }} @endif</p>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                @if ($item->action_url)
                                    <a href="{{ url($item->action_url) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                                        {{ __('ui.open') }}
                                    </a>
                                @endif
                                @if ($item->read_at === null)
                                    <button
                                        type="button"
                                        wire:click="markRead({{ $item->id }})"
                                        data-testid="mark-read-btn-{{ $item->id }}"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                                    >
                                        {{ __('ui.mark_read') }}
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    @elseif ($canManageRules)
        <div wire:key="alerts-tab-rules" class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('ui.create_alert_rule') }}</h2>
                <form wire:submit="createRule" class="mt-4 space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.rule_name') }}</label>
                        <input type="text" wire:model="ruleName" data-testid="rule-name-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @error('ruleName') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.rule_type') }}</label>
                        <select wire:model="ruleType" data-testid="rule-type-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="low_stock">low_stock</option>
                            <option value="sales_drop_anomaly">sales_drop_anomaly</option>
                            <option value="cancellation_spike">cancellation_spike</option>
                            <option value="sync_failure">sync_failure</option>
                            <option value="command_approval_needed">command_approval_needed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.branch_scope') }}</label>
                        <select wire:model="ruleBranchId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="">{{ __('ui.all_branches') }}</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.threshold_value') }}</label>
                        <input type="number" wire:model="thresholdValue" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <div class="flex flex-wrap gap-4 pt-1">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="channelInApp" class="rounded border-slate-300">
                            <span>{{ __('ui.channel_in_app') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="channelEmail" class="rounded border-slate-300">
                            <span>{{ __('ui.channel_email') }}</span>
                        </label>
                    </div>
                    <button type="submit" data-testid="create-rule-btn" class="w-full rounded-xl bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-600">
                        {{ __('ui.save_rule') }}
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2">
                <x-ui.data-table :empty="$rules->isEmpty()" :empty-message="__('ui.no_rules_found')">
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('ui.rule_name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.rule_type') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.channels') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($rules as $rule)
                        <tr>
                            <td class="px-4 py-3 text-xs font-semibold">{{ $rule->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-sky-700 dark:text-sky-300">{{ $rule->rule_type }}</td>
                            <td class="px-4 py-3 font-mono text-[11px]">
                                {{ $rule->channel_in_app ? 'InApp ' : '' }}{{ $rule->channel_email ? 'Email' : '' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.status-badge :variant="$rule->is_active ? 'live' : 'disconnected'" :dot="false">
                                    {{ $rule->is_active ? 'active' : 'paused' }}
                                </x-ui.status-badge>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <button type="button" wire:click="toggleRule({{ $rule->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                                    {{ $rule->is_active ? __('ui.pause') : __('ui.activate') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </div>
        </div>
    @endif
</div>
