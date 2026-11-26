<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Identity\Policies\TenantMembershipPolicy;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Policies\InventoryPolicy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Policies\OrderPolicy;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Organizations\Policies\BranchPolicy;
use App\Domain\Organizations\Policies\WarehousePolicy;
use App\Domain\Tenants\Services\TenantContext;
use App\Presentation\Livewire\BranchesManager;
use App\Presentation\Livewire\DashboardOverview;
use App\Presentation\Livewire\InventoryWorkspace;
use App\Presentation\Livewire\OrdersWorkspace;
use App\Presentation\Livewire\PlatformTenantsManager;
use App\Presentation\Livewire\SystemStatus;
use App\Presentation\Livewire\TeamManager;
use Illuminate\Support\Facades\Gate;
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

        Livewire::component('system-status', SystemStatus::class);
        Livewire::component('dashboard-overview', DashboardOverview::class);
        Livewire::component('branches-manager', BranchesManager::class);
        Livewire::component('team-manager', TeamManager::class);
        Livewire::component('platform-tenants-manager', PlatformTenantsManager::class);
        Livewire::component('inventory-workspace', InventoryWorkspace::class);
        Livewire::component('orders-workspace', OrdersWorkspace::class);
    }
}
