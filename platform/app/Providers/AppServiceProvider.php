<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Alerts\Models\PlatformNotification;
use App\Domain\Alerts\Policies\NotificationRulePolicy;
use App\Domain\Alerts\Policies\PlatformNotificationPolicy;
use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Analytics\Policies\PerformanceTargetPolicy;
use App\Domain\Commands\Models\RemoteCommand;
use App\Domain\Commands\Policies\RemoteCommandPolicy;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Identity\Policies\TenantMembershipPolicy;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Policies\DataSourcePolicy;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Policies\InventoryPolicy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Policies\OrderPolicy;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Organizations\Policies\BranchPolicy;
use App\Domain\Organizations\Policies\WarehousePolicy;
use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Reports\Policies\ScheduledReportPolicy;
use App\Domain\Tenants\Services\TenantContext;
use App\Presentation\Livewire\AlertsCenterWorkspace;
use App\Presentation\Livewire\BranchesManager;
use App\Presentation\Livewire\BusinessDashboardWorkspace;
use App\Presentation\Livewire\CommandCenterWorkspace;
use App\Presentation\Livewire\CustomersCrmWorkspace;
use App\Presentation\Livewire\DashboardOverview;
use App\Presentation\Livewire\FinancialsWorkspace;
use App\Presentation\Livewire\HeaderNotificationBell;
use App\Presentation\Livewire\IntegrationCenterWorkspace;
use App\Presentation\Livewire\InventoryWorkspace;
use App\Presentation\Livewire\OrdersWorkspace;
use App\Presentation\Livewire\PlatformTenantsManager;
use App\Presentation\Livewire\ProductsCatalogWorkspace;
use App\Presentation\Livewire\ReportsCenterWorkspace;
use App\Presentation\Livewire\SystemStatus;
use App\Presentation\Livewire\TeamManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(TenantMembership::class, TenantMembershipPolicy::class);
        Gate::policy(InventoryBalance::class, InventoryPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(DataSource::class, DataSourcePolicy::class);
        Gate::policy(RemoteCommand::class, RemoteCommandPolicy::class);
        Gate::policy(PerformanceTarget::class, PerformanceTargetPolicy::class);
        Gate::policy(NotificationRule::class, NotificationRulePolicy::class);
        Gate::policy(PlatformNotification::class, PlatformNotificationPolicy::class);
        Gate::policy(ScheduledReport::class, ScheduledReportPolicy::class);

        Livewire::component('system-status', SystemStatus::class);
        Livewire::component('dashboard-overview', DashboardOverview::class);
        Livewire::component('business-dashboard-workspace', BusinessDashboardWorkspace::class);
        Livewire::component('branches-manager', BranchesManager::class);
        Livewire::component('team-manager', TeamManager::class);
        Livewire::component('platform-tenants-manager', PlatformTenantsManager::class);
        Livewire::component('inventory-workspace', InventoryWorkspace::class);
        Livewire::component('orders-workspace', OrdersWorkspace::class);
        Livewire::component('integration-center-workspace', IntegrationCenterWorkspace::class);
        Livewire::component('products-catalog-workspace', ProductsCatalogWorkspace::class);
        Livewire::component('customers-crm-workspace', CustomersCrmWorkspace::class);
        Livewire::component('financials-workspace', FinancialsWorkspace::class);
        Livewire::component('command-center-workspace', CommandCenterWorkspace::class);
        Livewire::component('header-notification-bell', HeaderNotificationBell::class);
        Livewire::component('alerts-center-workspace', AlertsCenterWorkspace::class);
        Livewire::component('reports-center-workspace', ReportsCenterWorkspace::class);

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(15)->by(
            strtolower((string) $request->input('email', '')).'|'.$request->ip()
        ));

        RateLimiter::for('agent-enroll', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

        RateLimiter::for('agent-sync', fn (Request $request): Limit => Limit::perMinute(60)->by(
            ($request->attributes->get('connector_instance')?->id ?? 'anon').'|'.$request->ip()
        ));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(30)->by(
            (string) $request->route('dataSource').'|'.$request->ip()
        ));

        RateLimiter::for('reports-download', fn (Request $request): Limit => Limit::perMinute(20)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip()
        ));
    }
}
