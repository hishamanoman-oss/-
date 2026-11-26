<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Application\Services\AuditLogger;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

final class TeamManager extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showMemberModal = false;

    public ?int $editingMembershipId = null;

    public string $memberName = '';

    public string $memberEmail = '';

    public string $memberPassword = '';

    public ?int $selectedRoleId = null;

    /** @var list<int> */
    public array $selectedBranchIds = [];

    public string $branchAccessLevel = 'view';

    public function mount(): void
    {
        Gate::authorize('viewAny', TenantMembership::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openAddMember(): void
    {
        Gate::authorize('manage', TenantMembership::class);

        $this->resetValidation();
        $this->editingMembershipId = null;
        $this->memberName = '';
        $this->memberEmail = '';
        $this->memberPassword = '';
        $this->selectedRoleId = Role::query()->whereNull('tenant_id')->where('slug', 'observer')->value('id');
        $this->selectedBranchIds = [];
        $this->branchAccessLevel = 'view';
        $this->showMemberModal = true;
    }

    public function openEditMember(int $membershipId, TenantContext $tenantContext): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantContext->id())
            ->with(['user', 'roles', 'branchAccess'])
            ->findOrFail($membershipId);

        Gate::authorize('update', $membership);

        $this->resetValidation();
        $this->editingMembershipId = $membership->id;
        $this->memberName = $membership->user->name;
        $this->memberEmail = $membership->user->email;
        $this->memberPassword = '';
        $this->selectedRoleId = $membership->roles->first()?->id;
        $this->selectedBranchIds = $membership->branchAccess->pluck('branch_id')->map(fn ($id): int => (int) $id)->values()->all();
        $this->branchAccessLevel = (string) ($membership->branchAccess->first()?->access_level ?? 'view');
        $this->showMemberModal = true;
    }

    public function saveMember(TenantContext $tenantContext, AuditLogger $audit): void
    {
        $tenantId = $tenantContext->id();

        if ($this->editingMembershipId !== null) {
            $membership = TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($this->editingMembershipId);
            Gate::authorize('update', $membership);

            $validated = $this->validate([
                'selectedRoleId' => ['required', 'integer', 'exists:roles,id'],
                'selectedBranchIds' => ['array'],
                'selectedBranchIds.*' => ['integer'],
                'branchAccessLevel' => ['required', 'in:view,manage'],
            ]);

            $validBranchIds = Branch::query()
                ->whereIn('id', $validated['selectedBranchIds'] ?? [])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            DB::transaction(function () use ($membership, $validated, $validBranchIds): void {
                $membership->roles()->sync([(int) $validated['selectedRoleId']]);

                UserBranchAccess::query()->where('tenant_membership_id', $membership->id)->delete();
                foreach ($validBranchIds as $branchId) {
                    UserBranchAccess::query()->create([
                        'tenant_membership_id' => $membership->id,
                        'branch_id' => $branchId,
                        'access_level' => $validated['branchAccessLevel'],
                    ]);
                }
            });

            $audit->log('membership.updated', 'tenant_membership', $membership->id, [
                'role_id' => (int) $validated['selectedRoleId'],
                'branch_ids' => $validBranchIds,
                'access_level' => $validated['branchAccessLevel'],
            ]);
        } else {
            Gate::authorize('manage', TenantMembership::class);

            $validated = $this->validate([
                'memberName' => ['required', 'string', 'max:120'],
                'memberEmail' => ['required', 'email', 'max:160'],
                'memberPassword' => ['nullable', 'string', 'min:8'],
                'selectedRoleId' => ['required', 'integer', 'exists:roles,id'],
                'selectedBranchIds' => ['array'],
                'selectedBranchIds.*' => ['integer'],
                'branchAccessLevel' => ['required', 'in:view,manage'],
            ]);

            $validBranchIds = Branch::query()
                ->whereIn('id', $validated['selectedBranchIds'] ?? [])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $membership = DB::transaction(function () use ($tenantId, $validated, $validBranchIds): TenantMembership {
                $user = User::query()->where('email', $validated['memberEmail'])->first();
                if ($user === null) {
                    $user = User::query()->create([
                        'name' => trim($validated['memberName']),
                        'email' => strtolower(trim($validated['memberEmail'])),
                        'password' => Hash::make($validated['memberPassword'] ?: bin2hex(random_bytes(16))),
                        'is_platform_admin' => false,
                        'is_active' => true,
                        'preferred_locale' => 'ar',
                        'preferred_theme' => 'light',
                    ]);
                }

                $membership = TenantMembership::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'user_id' => $user->id],
                    ['status' => 'active', 'joined_at' => now()]
                );

                $membership->roles()->sync([(int) $validated['selectedRoleId']]);

                UserBranchAccess::query()->where('tenant_membership_id', $membership->id)->delete();
                foreach ($validBranchIds as $branchId) {
                    UserBranchAccess::query()->create([
                        'tenant_membership_id' => $membership->id,
                        'branch_id' => $branchId,
                        'access_level' => $validated['branchAccessLevel'],
                    ]);
                }

                return $membership;
            });

            $audit->log('membership.created', 'tenant_membership', $membership->id, [
                'email' => $validated['memberEmail'],
                'role_id' => (int) $validated['selectedRoleId'],
                'branch_ids' => $validBranchIds,
            ]);
        }

        $this->showMemberModal = false;
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function toggleUserActive(int $membershipId, TenantContext $tenantContext, AuditLogger $audit): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantContext->id())
            ->with('user')
            ->findOrFail($membershipId);

        Gate::authorize('update', $membership);

        if ((int) $membership->user_id === (int) Auth::id()) {
            $this->dispatch('toast', message: __('ui.cannot_deactivate_self'), type: 'error');

            return;
        }

        $user = $membership->user;
        $newActive = ! $user->is_active;
        $user->forceFill(['is_active' => $newActive])->save();

        if (! $newActive) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $audit->log('user.status_toggled', 'user', $user->id, [
            'is_active' => $newActive,
            'membership_id' => $membership->id,
        ]);

        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function render(TenantContext $tenantContext): View
    {
        Gate::authorize('viewAny', TenantMembership::class);

        $query = TenantMembership::query()
            ->where('tenant_id', $tenantContext->id())
            ->with(['user', 'roles', 'branches']);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->whereHas('user', function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        return view('livewire.team-manager', [
            'memberships' => $query->orderBy('id')->paginate(10),
            'roles' => Role::query()->whereNull('tenant_id')->orderBy('id')->get(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'canManageTeam' => Gate::allows('manage', TenantMembership::class),
        ]);
    }
}
