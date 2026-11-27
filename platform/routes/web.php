<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Web\AuthController;
use App\Presentation\Http\Controllers\Web\ContextController;
use App\Presentation\Http\Controllers\Web\OrderInvoiceController;
use App\Presentation\Http\Controllers\Web\ReportDownloadController;
use App\Presentation\Http\Controllers\Web\SystemStatusController;
use App\Presentation\Livewire\AlertsCenterWorkspace;
use App\Presentation\Livewire\BranchesManager;
use App\Presentation\Livewire\BusinessDashboardWorkspace;
use App\Presentation\Livewire\CommandCenterWorkspace;
use App\Presentation\Livewire\CustomersCrmWorkspace;
use App\Presentation\Livewire\FinancialsWorkspace;
use App\Presentation\Livewire\IntegrationCenterWorkspace;
use App\Presentation\Livewire\InventoryWorkspace;
use App\Presentation\Livewire\OrdersWorkspace;
use App\Presentation\Livewire\PlatformTenantsManager;
use App\Presentation\Livewire\ProductsCatalogWorkspace;
use App\Presentation\Livewire\ReportsCenterWorkspace;
use App\Presentation\Livewire\TeamManager;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    return ($user->is_platform_admin && $user->activeMemberships()->doesntExist())
        ? redirect()->route('platform.tenants')
        : redirect()->route('dashboard');
});
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

    Route::get('/dashboard', BusinessDashboardWorkspace::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/organization/branches', BranchesManager::class)
        ->middleware('permission:branches.view')
        ->name('organization.branches');

    Route::get('/team', TeamManager::class)
        ->middleware('permission:team.view')
        ->name('team.index');

    Route::get('/products', ProductsCatalogWorkspace::class)
        ->middleware('permission:inventory.view|sales.view')
        ->name('products.index');

    Route::get('/inventory', InventoryWorkspace::class)
        ->middleware('permission:inventory.view')
        ->name('inventory.index');

    Route::get('/orders', OrdersWorkspace::class)
        ->middleware('permission:sales.view')
        ->name('orders.index');

    Route::get('/orders/{order}/invoice', OrderInvoiceController::class)
        ->whereNumber('order')
        ->middleware('permission:sales.view')
        ->name('orders.invoice');

    Route::get('/customers', CustomersCrmWorkspace::class)
        ->middleware('permission:sales.view')
        ->name('customers.index');

    Route::get('/financials', FinancialsWorkspace::class)
        ->middleware('permission:reports.view|sales.view')
        ->name('financials.index');

    Route::get('/integrations', IntegrationCenterWorkspace::class)
        ->middleware('permission:integrations.view')
        ->name('integrations.index');

    Route::get('/commands', CommandCenterWorkspace::class)
        ->middleware('permission:commands.request|commands.approve|audit.view')
        ->name('commands.index');

    Route::get('/reports', ReportsCenterWorkspace::class)
        ->middleware('permission:reports.view')
        ->name('reports.index');

    Route::get('/reports/export', ReportDownloadController::class)
        ->middleware('permission:reports.view')
        ->name('reports.export');

    Route::get('/alerts', AlertsCenterWorkspace::class)
        ->name('alerts.index');

    Route::get('/platform/tenants', PlatformTenantsManager::class)
        ->middleware('platform.admin')
        ->name('platform.tenants');
});
