<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.commands_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.commands_subtitle') }}</p>
        </div>

        @if ($canRequest)
            <button
                type="button"
                wire:click="openCreateDrawer"
                data-testid="open-new-command-btn"
                class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
            >
                + {{ __('ui.new_remote_command') }}
            </button>
        @endif
    </div>

    <x-ui.data-table :empty="$commands->isEmpty()" :empty-message="__('ui.no_commands_found')">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <select wire:model.live="statusFilter" data-testid="commands-status-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="all">{{ __('ui.all_statuses') }}</option>
                    <option value="pending">pending</option>
                    <option value="approved">approved</option>
                    <option value="dispatched">dispatched</option>
                    <option value="executing">executing</option>
                    <option value="awaiting_reconciliation">awaiting_reconciliation</option>
                    <option value="succeeded">succeeded</option>
                    <option value="failed">failed</option>
                    <option value="expired">expired</option>
                    <option value="rejected">rejected</option>
                </select>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">ID</th>
                <th class="px-4 py-3 text-start">{{ __('ui.command_type') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.target_entity') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.requested_by') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.risk_level') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($commands as $cmd)
            @php
                $statusVariant = match ($cmd->status) {
                    'succeeded' => 'live',
                    'failed', 'rejected', 'expired' => 'error',
                    'dispatched', 'executing', 'awaiting_reconciliation' => 'syncing',
                    default => 'delayed',
                };
                $riskVariant = $cmd->risk_level === 'high' ? 'error' : ($cmd->risk_level === 'medium' ? 'delayed' : 'live');
            @endphp
            <tr data-testid="command-row-{{ $cmd->id }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3 font-mono text-xs font-bold">#{{ $cmd->id }}</td>
                <td class="px-4 py-3 font-mono text-xs font-semibold text-sky-700 dark:text-sky-300">{{ $cmd->command_type }}</td>
                <td class="px-4 py-3 text-xs">
                    <span class="font-mono">{{ $cmd->target_entity_type }} #{{ $cmd->target_external_id }}</span>
                    <span class="block text-[11px] text-slate-500">{{ $cmd->dataSource?->name }}</span>
                </td>
                <td class="px-4 py-3 text-xs">{{ $cmd->requestedBy?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$riskVariant" :dot="false">{{ strtoupper($cmd->risk_level) }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$statusVariant" data-testid="command-status-{{ $cmd->id }}">
                        {{ $cmd->status }}
                    </x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <div class="inline-flex flex-wrap justify-end gap-1.5">
                        @if ($canApprove && $cmd->status === 'pending')
                            <button
                                type="button"
                                wire:click="approveCommand({{ $cmd->id }})"
                                data-testid="approve-command-btn-{{ $cmd->id }}"
                                class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500"
                            >
                                {{ __('ui.approve') }}
                            </button>
                            <button
                                type="button"
                                wire:click="rejectCommand({{ $cmd->id }})"
                                data-testid="reject-command-btn-{{ $cmd->id }}"
                                class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
                            >
                                {{ __('ui.reject') }}
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="openDetail({{ $cmd->id }})"
                            data-testid="view-command-btn-{{ $cmd->id }}"
                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                        >
                            {{ __('ui.details') }}
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $commands->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    {{-- New Remote Command Drawer --}}
    <x-ui.drawer :show="$showCreateDrawer" :title="__('ui.new_remote_command')" close-action="$set('showCreateDrawer', false)">
        <form wire:submit="submitCommand" class="space-y-4 text-xs">
            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.data_source') }}</label>
                <select wire:model.live="dataSourceId" data-testid="cmd-source-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($writeSources as $ws)
                        <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.command_type') }}</label>
                <select wire:model.live="commandType" data-testid="cmd-type-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="product.price.update">{{ __('ui.cmd_product_price_update') }}</option>
                    <option value="order.status.update">{{ __('ui.cmd_order_status_update') }}</option>
                    <option value="inventory.stock.adjust">{{ __('ui.cmd_inventory_stock_adjust') }}</option>
                </select>
                @error('commandType') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.target_entity') }}</label>
                <select wire:model.live="targetInternalId" data-testid="cmd-target-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">--</option>
                    @foreach ($targets as $t)
                        <option value="{{ $t['id'] }}">{{ $t['label'] }}</option>
                    @endforeach
                </select>
                @error('targetInternalId') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
            </div>

            @if ($commandType === 'product.price.update')
                <div>
                    <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.new_unit_price') }}</label>
                    <input type="text" wire:model.live="newPrice" data-testid="cmd-new-price-input" placeholder="600.00" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('newPrice') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>
            @elseif ($commandType === 'order.status.update')
                <div>
                    <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.new_order_status') }}</label>
                    <select wire:model.live="newOrderStatus" data-testid="cmd-new-status-select" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="completed">completed</option>
                        <option value="partial">partial</option>
                        <option value="unpaid">unpaid</option>
                        <option value="refunded">refunded</option>
                        <option value="cancelled">cancelled</option>
                    </select>
                </div>
            @else
                <div>
                    <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.quantity_delta') }}</label>
                    <input type="text" wire:model.live="stockDelta" data-testid="cmd-stock-delta-input" placeholder="5.00" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('stockDelta') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.reason') }}</label>
                <input type="text" wire:model="reason" data-testid="cmd-reason-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('reason') <p class="mt-1 text-rose-600">{{ $message }}</p> @enderror
            </div>

            {{-- Precondition & Risk Preview --}}
            <div data-testid="cmd-risk-preview" class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                <p>{{ __('ui.precondition_current') }}: <span class="font-mono font-bold">{{ $preview['current_value'] ?? '—' }}</span></p>
                <p class="mt-1">
                    {{ __('ui.risk_level') }}:
                    <span class="font-bold {{ $preview['risk_level'] === 'high' ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ strtoupper($preview['risk_level']) }}
                    </span>
                    — {{ $preview['requires_approval'] ? __('ui.approval_required_note') : __('ui.auto_approved_note') }}
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showCreateDrawer', false)" class="rounded-lg border border-slate-300 px-4 py-2 dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" data-testid="cmd-submit-btn" class="rounded-lg bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-600">{{ __('ui.submit_command') }}</button>
            </div>
        </form>
    </x-ui.drawer>

    {{-- Command Detail Drawer --}}
    <x-ui.drawer :show="$showDetailDrawer && $selectedCommand !== null" :title="__('ui.command_details')" close-action="$set('showDetailDrawer', false)">
        @if ($selectedCommand)
            <div class="space-y-4 text-xs">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                    <div>
                        <p class="font-mono text-sm font-bold">#{{ $selectedCommand->id }} — {{ $selectedCommand->command_type }}</p>
                        <p class="font-mono text-slate-500">Key: {{ $selectedCommand->signing_key_id }} • TTL: {{ $selectedCommand->expires_at?->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <x-ui.status-badge :variant="$selectedCommand->status === 'succeeded' ? 'live' : ($selectedCommand->status === 'failed' ? 'error' : 'delayed')">
                        {{ $selectedCommand->status }}
                    </x-ui.status-badge>
                </div>

                <div class="space-y-2 rounded-lg bg-slate-50 p-3 font-mono dark:bg-slate-800/60">
                    <p><span class="font-bold">Ed25519 Signature:</span> {{ substr($selectedCommand->signature_ed25519, 0, 32) }}...</p>
                    <p><span class="font-bold">Precondition:</span> {{ json_encode($selectedCommand->precondition_json, JSON_UNESCAPED_UNICODE) }}</p>
                    <p><span class="font-bold">Payload:</span> {{ json_encode($selectedCommand->payload_json, JSON_UNESCAPED_UNICODE) }}</p>
                    @if ($selectedCommand->result_json)
                        <p class="text-emerald-700 dark:text-emerald-300"><span class="font-bold">Result:</span> {{ json_encode($selectedCommand->result_json, JSON_UNESCAPED_UNICODE) }}</p>
                    @endif
                    @if ($selectedCommand->error_message)
                        <p class="text-rose-600"><span class="font-bold">Error ({{ $selectedCommand->error_code }}):</span> {{ $selectedCommand->error_message }}</p>
                    @endif
                    <p><span class="font-bold">Reconciled At:</span> {{ $selectedCommand->reconciled_at?->format('Y-m-d H:i:s') ?? '—' }}</p>
                </div>

                @if ($selectedCommand->approvals->isNotEmpty())
                    <div>
                        <p class="mb-1.5 font-bold">{{ __('ui.approvals_history') }}</p>
                        @foreach ($selectedCommand->approvals as $ap)
                            <p class="font-mono text-[11px]">{{ $ap->decision }} by {{ $ap->approver?->name }} at {{ $ap->decided_at?->format('Y-m-d H:i:s') }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($selectedCommand->attempts->isNotEmpty())
                    <div>
                        <p class="mb-1.5 font-bold">{{ __('ui.attempts_history') }}</p>
                        @foreach ($selectedCommand->attempts as $att)
                            <p class="font-mono text-[11px]">Attempt #{{ $att->attempt_number }}: {{ $att->status }} ({{ $att->started_at?->format('H:i:s') }} -> {{ $att->finished_at?->format('H:i:s') ?? '...' }})</p>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-ui.drawer>
</div>
