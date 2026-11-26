<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Sync user's preferred locale and theme if not explicitly overridden in session
        if ($request->hasSession()) {
            if (! $request->session()->has('locale') && in_array($user->preferred_locale, ['ar', 'en'], true)) {
                $request->session()->put('locale', $user->preferred_locale);
                App::setLocale($user->preferred_locale);
                View::share('currentLocale', $user->preferred_locale);
                View::share('textDirection', $user->preferred_locale === 'ar' ? 'rtl' : 'ltr');
            }

            if (! $request->session()->has('theme') && in_array($user->preferred_theme, ['light', 'dark'], true)) {
                $request->session()->put('theme', $user->preferred_theme);
            }
        }

        $mode = (string) config('platform.deployment_mode', 'saas');

        if ($mode === 'self_hosted') {
            $this->resolveSelfHosted($request, $user);
        } else {
            $this->resolveSaas($request, $user);
        }

        $this->resolveBranchSelection($request);

        View::share('tenantContext', $this->tenantContext);

        return $next($request);
    }

    private function resolveSelfHosted(Request $request, \App\Models\User $user): void
    {
        $primaryTenant = Tenant::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($primaryTenant === null) {
            if ($user->is_platform_admin) {
                return;
            }
            abort(403, 'No active tenant configured for this self-hosted instance.');
        }

        $membership = TenantMembership::query()
            ->with('roles.permissions')
            ->where('tenant_id', $primaryTenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            if ($user->is_platform_admin) {
                $this->tenantContext->bind($primaryTenant, null);

                return;
            }

            $this->logoutSuspended($request);
        }

        $this->tenantContext->bind($primaryTenant, $membership);
        if ($request->hasSession()) {
            $request->session()->put('active_tenant_id', $primaryTenant->id);
        }
    }

    private function resolveSaas(Request $request, \App\Models\User $user): void
    {
        $requestedTenantId = $request->hasSession() ? $request->session()->get('active_tenant_id') : null;

        $membership = null;

        if ($requestedTenantId !== null) {
            $membership = TenantMembership::query()
                ->with(['tenant', 'roles.permissions'])
                ->where('user_id', $user->id)
                ->where('tenant_id', (int) $requestedTenantId)
                ->where('status', 'active')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
                ->first();
        }

        if ($membership === null) {
            $membership = TenantMembership::query()
                ->with(['tenant', 'roles.permissions'])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
        }

        if ($membership === null) {
            if ($user->is_platform_admin) {
                return;
            }

            $this->logoutSuspended($request);
        }

        $this->tenantContext->bind($membership->tenant, $membership);

        if ($request->hasSession()) {
            $request->session()->put('active_tenant_id', $membership->tenant_id);
        }
    }

    private function resolveBranchSelection(Request $request): void
    {
        if ($this->tenantContext->id() === null || $this->tenantContext->membership() === null) {
            return;
        }

        $sessionBranchId = $request->hasSession() ? $request->session()->get('active_branch_id') : null;
        if ($sessionBranchId !== null) {
            $branchId = (int) $sessionBranchId;
            $exists = Branch::query()->whereKey($branchId)->exists();

            if ($exists && $this->tenantContext->canAccessBranch($branchId)) {
                $this->tenantContext->setActiveBranchId($branchId);

                return;
            }

            if ($request->hasSession()) {
                $request->session()->forget('active_branch_id');
            }
        }

        $accessible = $this->tenantContext->accessibleBranchIds();
        if (is_array($accessible) && count($accessible) === 1) {
            $this->tenantContext->setActiveBranchId($accessible[0]);
            if ($request->hasSession()) {
                $request->session()->put('active_branch_id', $accessible[0]);
            }
        }
    }

    private function logoutSuspended(Request $request): never
    {
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        abort(403, 'No active tenant membership.');
    }
}
