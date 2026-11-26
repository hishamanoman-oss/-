<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Web;

use App\Application\Services\AuditLogger;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ContextController
{
    public function switchTenant(Request $request, TenantContext $tenantContext, AuditLogger $audit): RedirectResponse
    {
        if (config('platform.deployment_mode') === 'self_hosted') {
            abort(403, 'Tenant switching is disabled in self-hosted mode.');
        }

        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $membership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', (int) $validated['tenant_id'])
            ->where('status', 'active')
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->first();

        if ($membership === null) {
            abort(403, 'Unauthorized tenant switch.');
        }

        $previousTenantId = $tenantContext->id();
        $request->session()->put('active_tenant_id', $membership->tenant_id);
        $request->session()->forget('active_branch_id');

        $audit->log(
            action: 'tenant.switched',
            entityType: 'tenant',
            entityId: $membership->tenant_id,
            metadata: [
                'from_tenant_id' => $previousTenantId,
                'to_tenant_id' => $membership->tenant_id,
            ],
            tenantId: $membership->tenant_id,
            actorUserId: $user->id,
        );

        return redirect()->route('dashboard');
    }

    public function switchBranch(Request $request, TenantContext $tenantContext): RedirectResponse
    {
        $branchInput = $request->input('branch_id');

        if ($branchInput === null || $branchInput === '' || $branchInput === 'all') {
            if (! $tenantContext->hasFullBranchAccess()) {
                $accessible = $tenantContext->accessibleBranchIds() ?? [];
                if (count($accessible) > 1) {
                    $request->session()->forget('active_branch_id');

                    return back();
                }
                abort(403, 'Branch scope restriction requires a specific branch.');
            }

            $request->session()->forget('active_branch_id');

            return back();
        }

        $branchId = (int) $branchInput;
        $branch = Branch::query()->find($branchId);

        if ($branch === null || ! $tenantContext->canAccessBranch($branchId)) {
            abort(403, 'Unauthorized branch selection.');
        }

        $request->session()->put('active_branch_id', $branchId);

        return back();
    }

    public function switchTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->session()->put('theme', $validated['theme']);

        $user = $request->user();
        if ($user instanceof User) {
            $user->forceFill(['preferred_theme' => $validated['theme']])->save();
        }

        return back();
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        $user = $request->user();
        if ($user instanceof User) {
            $user->forceFill(['preferred_locale' => $locale])->save();
        }

        return back();
    }
}
