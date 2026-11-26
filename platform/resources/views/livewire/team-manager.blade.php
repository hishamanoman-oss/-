<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('ui.team_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ui.team_subtitle') }}</p>
        </div>

        @if ($canManageTeam)
            <button
                type="button"
                wire:click="openAddMember"
                data-testid="add-member-btn"
                class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600"
            >
                + {{ __('ui.add_member') }}
            </button>
        @endif
    </div>

    <x-ui.data-table :empty="$memberships->isEmpty()">
        <x-slot:toolbar>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('ui.search_placeholder') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950 sm:max-w-xs"
            >
        </x-slot:toolbar>

        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-start">{{ __('ui.name') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.email') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.role') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.branch_access') }}</th>
                <th class="px-4 py-3 text-start">{{ __('ui.user_status') }}</th>
                <th class="px-4 py-3 text-end">{{ __('ui.actions') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($memberships as $m)
            <tr data-testid="member-row-{{ $m->id }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-4 py-3 font-medium">{{ $m->user->name }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $m->user->email }}</td>
                <td class="px-4 py-3">
                    @foreach ($m->roles as $role)
                        <x-ui.status-badge variant="info" :dot="false">{{ $role->name }}</x-ui.status-badge>
                    @endforeach
                </td>
                <td class="px-4 py-3 text-xs">
                    @php
                        $roleSlugs = $m->roles->pluck('slug')->all();
                        $isTenantWide = in_array('tenant_owner', $roleSlugs, true) || in_array('general_manager', $roleSlugs, true);
                    @endphp
                    @if ($isTenantWide)
                        <span class="text-slate-500 dark:text-slate-400">{{ __('ui.all_branches_role') }}</span>
                    @elseif ($m->branches->isEmpty())
                        <span class="text-amber-600 dark:text-amber-400">{{ __('ui.no_branch_assigned') }}</span>
                    @else
                        <div class="flex flex-wrap gap-1">
                            @foreach ($m->branches as $b)
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] dark:bg-slate-800">
                                    {{ $b->name }} ({{ $b->pivot->access_level }})
                                </span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.status-badge :variant="$m->user->is_active ? 'active' : 'inactive'">
                        {{ $m->user->is_active ? __('ui.active') : __('ui.inactive') }}
                    </x-ui.status-badge>
                </td>
                <td class="px-4 py-3 text-end">
                    @if ($canManageTeam)
                        <div class="inline-flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="openEditMember({{ $m->id }})"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            >
                                {{ __('ui.edit') }}
                            </button>
                            <button
                                type="button"
                                wire:click="toggleUserActive({{ $m->id }})"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            >
                                {{ $m->user->is_active ? __('ui.deactivate') : __('ui.activate') }}
                            </button>
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach

        <x-slot:pagination>
            {{ $memberships->links() }}
        </x-slot:pagination>
    </x-ui.data-table>

    <x-ui.drawer :show="$showMemberModal" :title="$editingMembershipId ? __('ui.edit_member') : __('ui.add_member')" close-action="$set('showMemberModal', false)">
        <form wire:submit="saveMember" class="space-y-4">
            @if (! $editingMembershipId)
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.name') }}</label>
                    <input type="text" wire:model="memberName" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('memberName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.email') }}</label>
                    <input type="email" wire:model="memberEmail" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('memberEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.password') }}</label>
                    <input type="password" wire:model="memberPassword" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @error('memberPassword') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            @else
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $memberName }} ({{ $memberEmail }})</p>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.role') }}</label>
                    <select wire:model="selectedRoleId" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.access_level') }}</label>
                    <select wire:model="branchAccessLevel" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                        <option value="view">{{ __('ui.access_view') }}</option>
                        <option value="manage">{{ __('ui.access_manage') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('ui.branch_access') }}</label>
                <div class="max-h-36 space-y-1.5 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    @foreach ($branches as $b)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedBranchIds" value="{{ $b->id }}" class="rounded border-slate-300">
                            <span>{{ $b->name }} ({{ $b->code }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="$set('showMemberModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">{{ __('ui.save') }}</button>
            </div>
        </form>
    </x-ui.drawer>
</div>
