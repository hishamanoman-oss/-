<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Web;

use App\Application\Services\AuditLogger;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthController
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            return redirect()->intended($user->is_platform_admin && $user->activeMemberships()->doesntExist() ? route('platform.tenants') : route('dashboard'));
        }

        return view('auth.login');
    }

    public function login(Request $request, AuditLogger $audit, TenantContext $tenantContext): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => __('auth.throttle', ['seconds' => $seconds]),
                ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $remember)) {
            RateLimiter::hit($throttleKey, 60);

            $audit->log(
                action: 'auth.login_failed',
                entityType: 'user',
                entityId: null,
                metadata: ['email' => $credentials['email']],
                tenantId: null,
                actorUserId: null,
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $audit->log(
                action: 'auth.login_blocked_disabled',
                entityType: 'user',
                entityId: $user->id,
                metadata: ['email' => $user->email],
                tenantId: null,
                actorUserId: $user->id,
            );

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.account_disabled')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        $user->forceFill(['last_login_at' => now()])->save();

        $defaultMembership = $tenantContext->runWithoutTenantScope(fn () => $user->activeMemberships()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first());

        if ($defaultMembership !== null) {
            $request->session()->put('active_tenant_id', $defaultMembership->tenant_id);
        } elseif (! $user->is_platform_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.no_active_membership')]);
        }

        $request->session()->put('locale', in_array($user->preferred_locale, ['ar', 'en'], true) ? $user->preferred_locale : 'ar');
        $request->session()->put('theme', in_array($user->preferred_theme, ['light', 'dark'], true) ? $user->preferred_theme : 'light');

        $audit->log(
            action: 'auth.login',
            entityType: 'user',
            entityId: $user->id,
            metadata: ['email' => $user->email],
            tenantId: $defaultMembership?->tenant_id,
            actorUserId: $user->id,
        );

        $fallbackRoute = ($user->is_platform_admin && $defaultMembership === null)
            ? route('platform.tenants')
            : route('dashboard');

        return redirect()->intended($fallbackRoute);
    }

    public function logout(Request $request, AuditLogger $audit, TenantContext $tenantContext): RedirectResponse
    {
        $userId = Auth::id();
        $tenantId = $tenantContext->id();

        if ($userId !== null) {
            $audit->log(
                action: 'auth.logout',
                entityType: 'user',
                entityId: $userId,
                metadata: [],
                tenantId: $tenantId,
                actorUserId: $userId,
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
