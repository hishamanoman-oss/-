<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.integrations_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.integrations_subtitle') }}</p>
        </div>

        @if ($canManage)
            <button
                type="button"
                wire:click="openCreateSource"
                data-testid="add-source-btn"
                class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
            >
                + {{ __('ui.add_data_source') }}
            </button>
        @endif
    </div>

    {{-- Connection Test Result Banner --}}
    @if ($connectionTestResult !== null)
        <div data-testid="connection-test-banner" class="rounded-xl border p-4 text-sm {{ $connectionTestResult['ok'] ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200' }}">
            {{ $connectionTestResult['message'] }}
        </div>
    @endif

    {{-- One-Time Enrollment Code Banner --}}
    @if ($generatedEnrollmentCode !== null)
        <div data-testid="enrollment-code-banner" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100">
            <p class="font-bold">{{ __('ui.enrollment_code_once') }}</p>
            <p class="mt-1 font-mono text-base font-bold tracking-wider" data-testid="generated-enrollment-code">{{ $generatedEnrollmentCode }}</p>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-800">
        @foreach (['sources' => __('ui.tab_sources'), 'enrollment' => __('ui.tab_enrollment'), 'mappings' => __('ui.tab_mappings'), 'runs' => __('ui.tab_runs')] as $tabKey => $tabLabel)
            <button
                type="button"
                wire:click="setTab('{{ $tabKey }}')"
                data-testid="tab-{{ $tabKey }}"
                class="rounded-lg px-3.5 py-2 text-xs font-semibold transition {{ $activeTab === $tabKey ? 'bg-sky-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800' }}"
            >
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{-- Tab 1: Data Sources --}}
    @if ($activeTab === 'sources')
        <x-ui.data-table :empty="$sources->isEmpty()" :empty-message="__('ui.no_sources_found')">
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('ui.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.connector_type') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.capture_mode') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.freshness') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.last_sync') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($sources as $src)
                <tr data-testid="source-row-{{ $src->id }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 {{ $selectedSource && $selectedSource->id === $src->id ? 'bg-sky-50/40 dark:bg-sky-950/20' : '' }}">
                    <td class="px-4 py-3">
                        <button type="button" wire:click="selectSource({{ $src->id }})" class="text-start font-semibold text-slate-900 hover:text-sky-600 dark:text-white">
                            {{ $src->name }}
                        </button>
                        <p class="text-[11px] text-slate-500">{{ $src->instances_count }} {{ __('ui.devices') }} • {{ $src->field_mappings_count }} {{ __('ui.mappings') }}</p>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $src->connector_type }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $src->capture_mode }}</td>
                    <td class="px-4 py-3">
                        <x-ui.status-badge :variant="$src->freshness_status" data-testid="source-freshness-{{ $src->id }}">
                            {{ __('ui.sync_'.$src->freshness_status) }}
                        </x-ui.status-badge>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">
                        {{ $src->last_successful_sync_at?->format('Y-m-d H:i:s') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-end">
                        @if ($canManage)
                            <div class="inline-flex flex-wrap justify-end gap-1.5">
                                <button type="button" wire:click="testConnection({{ $src->id }})" data-testid="test-conn-btn-{{ $src->id }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                                    {{ __('ui.test_connection') }}
                                </button>
                                <button type="button" wire:click="discoverSchema({{ $src->id }})" data-testid="discover-schema-btn-{{ $src->id }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                                    {{ __('ui.discover_schema') }}
                                </button>
                                <button type="button" wire:click="runSyncNow({{ $src->id }})" data-testid="run-sync-btn-{{ $src->id }}" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500">
                                    {{ __('ui.run_sync_now') }}
                                </button>
                                <button type="button" wire:click="toggleSourceStatus({{ $src->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                                    {{ $src->status === 'paused' ? __('ui.resume') : __('ui.pause') }}
                                </button>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.data-table>
    @endif

    {{-- Tab 2: Enrollment & Devices --}}
    @if ($activeTab === 'enrollment' && $selectedSource)
        <div class="space-y-4">
            @if ($canManage)
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.device_name') }}</label>
                        <input type="text" wire:model="newDeviceName" data-testid="new-device-name-input" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </div>
                    <button type="button" wire:click="generateEnrollmentCode({{ $selectedSource->id }})" data-testid="generate-enrollment-btn" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">
                        {{ __('ui.generate_enrollment_code') }}
                    </button>
                </div>
            @endif

            <x-ui.data-table :empty="$instances->isEmpty()" :empty-message="__('ui.no_instances_found')">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.device_name') }}</th>
                        <th class="px-4 py-3 text-start">Device ID</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.last_seen') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($instances as $inst)
                    <tr data-testid="instance-row-{{ $inst->id }}">
                        <td class="px-4 py-3 font-medium">{{ $inst->device_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $inst->device_id }}</td>
                        <td class="px-4 py-3">
                            <x-ui.status-badge :variant="$inst->status === 'active' ? 'active' : ($inst->status === 'revoked' ? 'error' : 'delayed')">
                                {{ $inst->status }}
                            </x-ui.status-badge>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $inst->last_seen_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            @if ($canManage && $inst->status !== 'revoked')
                                <button type="button" wire:click="revokeInstance({{ $inst->id }})" data-testid="revoke-instance-btn-{{ $inst->id }}" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                                    {{ __('ui.revoke_token') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </div>
    @endif

    {{-- Tab 3: Schema Discovery & Field Mappings --}}
    @if ($activeTab === 'mappings' && $selectedSource)
        <div class="space-y-5">
            @if ($discoveredSchema !== [])
                <div data-testid="discovered-schema-panel" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-white">{{ __('ui.discovered_schema_title') }}</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($discoveredSchema as $tbl => $cols)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                                <p class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300">{{ $tbl }}</p>
                                <p class="mt-1 font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ implode(', ', array_keys($cols)) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($canManage)
                <form wire:submit="saveMapping" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-6">
                    <select wire:model="mapEntity" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                        <option value="branch">branch</option>
                        <option value="warehouse">warehouse</option>
                        <option value="customer">customer</option>
                        <option value="product">product</option>
                        <option value="inventory_balance">inventory_balance</option>
                        <option value="order">order</option>
                        <option value="order_item">order_item</option>
                    </select>
                    <input type="text" wire:model="mapSourceTable" placeholder="Source Table" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950">
                    <input type="text" wire:model="mapSourceColumn" placeholder="Source Column" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950">
                    <input type="text" wire:model="mapCanonicalField" placeholder="Canonical Field" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-950">
                    <select wire:model="mapTransformRule" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                        <option value="">No transform</option>
                        <option value="trim">trim</option>
                        <option value="uppercase">uppercase</option>
                        <option value="decimal">decimal</option>
                        <option value="iso_date">iso_date</option>
                        <option value="map_status">map_status</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
                </form>
            @endif

            <x-ui.data-table :empty="$mappings->isEmpty()">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">Canonical Entity</th>
                        <th class="px-4 py-3 text-start">Source Table.Column</th>
                        <th class="px-4 py-3 text-start">Canonical Field</th>
                        <th class="px-4 py-3 text-start">Transform</th>
                        <th class="px-4 py-3 text-start">Required</th>
                    </tr>
                </x-slot:head>
                @foreach ($mappings as $map)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs font-semibold">{{ $map->canonical_entity }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-sky-700 dark:text-sky-300">{{ $map->source_table }}.{{ $map->source_column }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $map->canonical_field }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $map->transform_rule ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-xs">{{ $map->is_required ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </div>
    @endif

    {{-- Tab 4: Sync Runs, Errors & Reconciliation --}}
    @if ($activeTab === 'runs' && $selectedSource)
        <div class="space-y-5">
            @if ($canManage)
                <div class="flex justify-end">
                    <button type="button" wire:click="runReconciliation({{ $selectedSource->id }})" data-testid="run-reconcile-btn" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ __('ui.run_reconciliation') }}
                    </button>
                </div>
            @endif

            @if ($reconciliationResult !== null)
                <div data-testid="reconciliation-panel" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold">{{ __('ui.reconciliation_report') }}</h3>
                        <x-ui.status-badge :variant="$reconciliationResult['in_sync'] ? 'live' : 'delayed'">
                            {{ $reconciliationResult['in_sync'] ? 'In Sync (0 Drift)' : 'Drift Detected' }}
                        </x-ui.status-badge>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        @foreach ($reconciliationResult['entities'] as $entity => $stats)
                            <div class="rounded-lg border border-slate-200 p-2.5 dark:border-slate-800">
                                <p class="font-mono font-bold">{{ $entity }}</p>
                                <p class="text-slate-500">{{ $stats['source_table'] }}: {{ $stats['source_count'] }} / Mapped: {{ $stats['canonical_mapped_count'] }}</p>
                                <p class="font-mono {{ $stats['drift'] === 0 ? 'text-emerald-600' : 'text-amber-600' }}">Drift: {{ $stats['drift'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-ui.data-table :empty="$syncRuns->isEmpty()" :empty-message="__('ui.no_runs_found')">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">ID</th>
                        <th class="px-4 py-3 text-start">Type</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                        <th class="px-4 py-3 text-end">Received</th>
                        <th class="px-4 py-3 text-end">Upserted</th>
                        <th class="px-4 py-3 text-end">Skipped Dup</th>
                        <th class="px-4 py-3 text-end">Failed</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.timestamp') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($syncRuns as $run)
                    <tr data-testid="sync-run-row-{{ $run->id }}">
                        <td class="px-4 py-3 font-mono text-xs">#{{ $run->id }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $run->run_type }}</td>
                        <td class="px-4 py-3">
                            <x-ui.status-badge :variant="$run->status === 'succeeded' ? 'live' : ($run->status === 'failed' ? 'error' : 'delayed')">
                                {{ $run->status }}
                            </x-ui.status-badge>
                        </td>
                        <td class="px-4 py-3 text-end font-mono">{{ $run->records_received }}</td>
                        <td class="px-4 py-3 text-end font-mono text-emerald-600">{{ $run->records_upserted }}</td>
                        <td class="px-4 py-3 text-end font-mono text-slate-500">{{ $run->records_skipped_duplicate }}</td>
                        <td class="px-4 py-3 text-end font-mono text-rose-600">{{ $run->records_failed }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $run->finished_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($syncErrors->isNotEmpty())
                <x-ui.data-table>
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-start">Entity</th>
                            <th class="px-4 py-3 text-start">External ID</th>
                            <th class="px-4 py-3 text-start">Error</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($syncErrors as $err)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $err->entity_type }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $err->external_id }}</td>
                            <td class="px-4 py-2 text-xs text-rose-600">{{ $err->error_message }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </div>
    @endif

    <x-ui.drawer :show="$showCreateSourceModal" :title="__('ui.add_data_source')" close-action="$set('showCreateSourceModal', false)">
        <form wire:submit="saveSource" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.name') }}</label>
                <input type="text" wire:model="sourceName" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.connector_type') }}</label>
                    <select wire:model="connectorType" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="local_mysql_agent">local_mysql_agent</option>
                        <option value="local_mysql_direct_test">local_mysql_direct_test</option>
                        <option value="webhook_generic">webhook_generic</option>
                        <option value="csv_import">csv_import</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.capture_mode') }}</label>
                    <select wire:model="captureMode" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="incremental_polling">incremental_polling</option>
                        <option value="cdc_binlog">cdc_binlog</option>
                        <option value="webhooks">webhooks</option>
                        <option value="manual_csv">manual_csv</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showCreateSourceModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>
</div>
