<?php

declare(strict_types=1);

namespace App\Presentation\Livewire;

use App\Application\Services\AuditLogger;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Models\License;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\Ed25519LicenseService;
use App\Domain\Tenants\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class PlatformTenantsManager extends Component
{
    public bool $showCreateModal = false;

    public string $tenantName = '';

    public string $tenantSlug = '';

    public string $defaultCurrency = 'SAR';

    public string $defaultLocale = 'ar';

    public string $initialBranchName = '';

    public string $initialBranchCity = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->is_platform_admin === true, 403);
    }

    public function openCreateTenant(): void
    {
        abort_unless(Auth::user()?->is_platform_admin === true, 403);

        $this->resetValidation();
        $this->tenantName = '';
        $this->tenantSlug = '';
        $this->defaultCurrency = 'SAR';
        $this->defaultLocale = 'ar';
        $this->initialBranchName = '';
        $this->initialBranchCity = '';
        $this->showCreateModal = true;
    }

    public function createTenant(TenantContext $tenantContext, AuditLogger $audit): void
    {
        abort_unless(Auth::user()?->is_platform_admin === true, 403);

        $validated = $this->validate([
            'tenantName' => ['required', 'string', 'max:160'],
            'tenantSlug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('tenants', 'slug')],
            'defaultCurrency' => ['required', 'string', 'size:3'],
            'defaultLocale' => ['required', 'in:ar,en'],
            'initialBranchName' => ['nullable', 'string', 'max:150'],
            'initialBranchCity' => ['nullable', 'string', 'max:80'],
        ]);

        $tenant = $tenantContext->runWithoutTenantScope(function () use ($validated): Tenant {
            return DB::transaction(function () use ($validated): Tenant {
                $tenant = Tenant::query()->create([
                    'name' => trim($validated['tenantName']),
                    'slug' => Str::lower(trim($validated['tenantSlug'])),
                    'status' => 'active',
                    'default_currency' => strtoupper($validated['defaultCurrency']),
                    'default_timezone' => 'Asia/Riyadh',
                    'default_locale' => $validated['defaultLocale'],
                ]);

                if (! empty($validated['initialBranchName'])) {
                    $branch = Branch::query()->create([
                        'tenant_id' => $tenant->id,
                        'code' => 'BR-MAIN',
                        'name' => trim($validated['initialBranchName']),
                        'city' => trim((string) ($validated['initialBranchCity'] ?: 'Riyadh')),
                        'status' => 'active',
                    ]);

                    Warehouse::query()->create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'code' => 'WH-MAIN',
                        'name' => 'Main Warehouse',
                        'is_default' => true,
                        'status' => 'active',
                    ]);
                }

                return $tenant;
            });
        });

        $audit->log('platform.tenant_created', 'tenant', $tenant->id, ['slug' => $tenant->slug], tenantId: null);

        $this->showCreateModal = false;
        $this->dispatch('toast', message: __('ui.created_successfully'), type: 'success');
    }

    public function toggleTenantStatus(int $tenantId, TenantContext $tenantContext, AuditLogger $audit): void
    {
        abort_unless(Auth::user()?->is_platform_admin === true, 403);

        $tenant = $tenantContext->runWithoutTenantScope(fn () => Tenant::query()->findOrFail($tenantId));
        $newStatus = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->update(['status' => $newStatus]);

        $audit->log('platform.tenant_status_toggled', 'tenant', $tenant->id, ['status' => $newStatus], tenantId: null);
        $this->dispatch('toast', message: __('ui.saved_successfully'), type: 'success');
    }

    public function render(TenantContext $tenantContext, Ed25519LicenseService $licenseService): View
    {
        abort_unless(Auth::user()?->is_platform_admin === true, 403);

        $tenants = $tenantContext->runWithoutTenantScope(fn () => Tenant::query()
            ->withCount(['branches', 'memberships'])
            ->with(['licenses' => fn ($q) => $q->latest('id')])
            ->orderBy('id')
            ->get());

        $licenses = License::query()->with('tenant:id,name,slug')->latest('id')->get();

        return view('livewire.platform-tenants-manager', [
            'tenants' => $tenants,
            'licenses' => $licenses,
            'deploymentMode' => (string) config('platform.deployment_mode', 'saas'),
            'hasLicenseKeyConfigured' => (string) config('platform.license_public_key', '') !== '',
            'licenseService' => $licenseService,
        ]);
    }
}
