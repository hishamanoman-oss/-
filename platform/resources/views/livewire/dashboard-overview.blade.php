<div class="space-y-6">
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ __('ui.welcome_back', ['name' => $user?->name ?? '']) }}
            </h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $tenant?->name ?? '' }}
                <span class="mx-1.5">•</span>
                <span>{{ __('ui.current_role') }}:</span>
                @foreach ($roles as $role)
                    <x-ui.status-badge variant="info" :dot="false">{{ $role->name }}</x-ui.status-badge>
                @endforeach
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status-badge variant="disconnected">
                {{ __('ui.sync_disconnected') }}
            </x-ui.status-badge>
            <x-ui.status-badge variant="active" :dot="false">
                {{ $deploymentMode === 'self_hosted' ? __('ui.deployment_self_hosted') : __('ui.deployment_saas') }}
            </x-ui.status-badge>
        </div>
    </div>

    {{-- Live KPI Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.kpi-card
            :label="__('ui.branches_total')"
            :value="$branchesCount"
            :hint="__('ui.active').': '.$activeBranchesCount"
            accent="sky"
            data-testid="kpi-branches-count"
        />
        <x-ui.kpi-card
            :label="__('ui.warehouses_total')"
            :value="$warehousesCount"
            accent="emerald"
            data-testid="kpi-warehouses-count"
        />
        <x-ui.kpi-card
            :label="__('ui.team_members_total')"
            :value="$teamCount"
            accent="amber"
            data-testid="kpi-team-count"
        />
        <x-ui.kpi-card
            :label="__('ui.branch_scope_label')"
            :value="$activeBranch ? $activeBranch->name : __('ui.full_tenant_scope')"
            :hint="$activeBranch?->code"
            accent="sky"
            data-testid="kpi-branch-scope"
        />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Authorized Branches --}}
        <section class="space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.accessible_branches') }}</h2>
            <x-ui.data-table :empty="$visibleBranches->isEmpty()">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.code') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.name') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.city') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.warehouses') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($visibleBranches as $branch)
                    <tr data-testid="dashboard-branch-row-{{ $branch->id }}">
                        <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $branch->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $branch->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $branch->city }}</td>
                        <td class="px-4 py-3 font-mono">{{ $branch->warehouses_count }}</td>
                        <td class="px-4 py-3">
                            <x-ui.status-badge :variant="$branch->status">{{ __('ui.'.$branch->status) }}</x-ui.status-badge>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </section>

        {{-- Recent Governance Audit Logs --}}
        <section class="space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.recent_audit_logs') }}</h2>
            <x-ui.data-table :empty="$recentAudits->isEmpty()" :empty-message="__('ui.no_audit_logs')">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('ui.action') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.actor') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('ui.timestamp') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($recentAudits as $log)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-sky-700 dark:text-sky-300">{{ $log->action }}</td>
                        <td class="px-4 py-3 text-xs">{{ $log->actor?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </section>
    </div>
</div>
