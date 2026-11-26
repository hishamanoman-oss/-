<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Web\AuthController;
use App\Presentation\Http\Controllers\Web\ContextController;
use App\Presentation\Http\Controllers\Web\SystemStatusController;
use App\Presentation\Livewire\BranchesManager;
use App\Presentation\Livewire\DashboardOverview;
use App\Presentation\Livewire\PlatformTenantsManager;
use App\Presentation\Livewire\TeamManager;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));
Route::get('/system-status', SystemStatusController::class)->name('system.status');

Route::get('/preferences/locale/{locale}', [ContextController::class, 'switchLocale'])->name('preferences.locale');
Route::post('/preferences/theme', [ContextController::class, 'switchTheme'])->name('preferences.theme');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'active.user', 'tenant.context'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::post('/context/switch-tenant', [ContextController::class, 'switchTenant'])->name('context.switch-tenant');
    Route::post('/context/switch-branch', [ContextController::class, 'switchBranch'])->name('context.switch-branch');

    Route::get('/dashboard', DashboardOverview::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/organization/branches', BranchesManager::class)
        ->middleware('permission:branches.view')
        ->name('organization.branches');

    Route::get('/team', TeamManager::class)
        ->middleware('permission:team.view')
        ->name('team.index');

    Route::get('/platform/tenants', PlatformTenantsManager::class)
        ->middleware('platform.admin')
        ->name('platform.tenants');
});
