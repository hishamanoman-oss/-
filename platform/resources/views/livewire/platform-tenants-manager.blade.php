<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.platform_tenants_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.platform_tenants_subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.status-badge variant="info" :dot="false">
                {{ $deploymentMode === 'self_hosted' ? __('ui.deployment_self_hosted') : __('ui.deployment_saas') }}
            </x-ui.status-badge>
            <button
                type="button"
                wire:click="openCreateTenant"
                data-testid="create-tenant-btn"
                class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
            >
                + {{ __('ui.create_tenant') }}
            </button>
        </div>
    </div>

    <x-ui.data-table :empty="$tenants->isEmpty()">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.tenant_name') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.tenant_slug') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.currency') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.branches_count') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.members_count') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($tenants as $t)
            <tr data-testid="platform-tenant-row-{{ $t->slug }}">
                <td class="px-4 py-3 font-medium">{{ $t->name }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $t->slug }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $t->default_currency }}</td>
                <td class="px-4 py-3 font-mono">{{ $t->branches_count }}</td>
                <td class="px-4 py-3 font-mono">{{ $t->memberships_count }}</td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$t->status">{{ __('ui.'.$t->status) }}</x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end">
                    <button
                        type="button"
                        wire:click="toggleTenantStatus({{ $t->id }})"
                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        {{ $t->status === 'active' ? __('ui.deactivate') : __('ui.activate') }}
                    </button>
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <section class="space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ __('ui.license_registry') }}</h2>
        <x-ui.data-table :empty="$licenses->isEmpty()" :empty-message="__('ui.no_licenses')">
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('ui.license_key_id') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.edition') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.tenant_name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.expires_at') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('ui.status') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($licenses as $lic)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $lic->license_key_id }}</td>
                    <td class="px-4 py-3">{{ $lic->edition }}</td>
                    <td class="px-4 py-3">{{ $lic->tenant?->name ?? 'Instance-Wide' }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $lic->expires_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">
                        <x-ui.status-badge :variant="$lic->status === 'active' ? 'active' : 'error'">{{ $lic->status }}</x-ui.status-badge>
                    </td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </section>

    <x-ui.drawer :show="$showCreateModal" :title="__('ui.create_tenant')" close-action="$set('showCreateModal', false)">
        <form wire:submit="createTenant" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.tenant_name') }}</label>
                <input type="text" wire:model="tenantName" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('tenantName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.tenant_slug') }}</label>
                <input type="text" wire:model="tenantSlug" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                @error('tenantSlug') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.currency') }}</label>
                    <input type="text" wire:model="defaultCurrency" maxlength="3" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.locale') }}</label>
                    <select wire:model="defaultLocale" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="ar">ar</option>
                        <option value="en">en</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.initial_branch_name') }}</label>
                    <input type="text" wire:model="initialBranchName" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.initial_branch_city') }}</label>
                    <input type="text" wire:model="initialBranchCity" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>
</div>
