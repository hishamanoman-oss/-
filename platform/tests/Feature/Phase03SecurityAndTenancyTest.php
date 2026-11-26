<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Tenants\Exceptions\MissingTenantContextException;
use App\Domain\Tenants\Models\License;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\Ed25519LicenseService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use App\Presentation\Livewire\BranchesManager;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

final class Phase03SecurityAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_users_table_has_no_direct_tenant_id_and_uses_tenant_memberships(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasTable('tenant_memberships'));

        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $this->assertSame(2, $owner->activeMemberships()->count());
    }

    public function test_belongs_to_tenant_fails_closed_without_tenant_context(): void
    {
        app(TenantContext::class)->clear();

        $this->expectException(MissingTenantContextException::class);

        Branch::query()->count();
    }

    public function test_cross_tenant_isolation_blocks_access_to_other_tenant_resources(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);

        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());
        $branchB1 = $ctx->runWithoutTenantScope(fn () => Branch::query()->where('tenant_id', $tenantB->id)->firstOrFail());

        // Branch1 Manager only belongs to Tenant A
        $branch1Mgr = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        $this->actingAs($branch1Mgr)
            ->post('/context/switch-tenant', ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        // Attempting to switch active branch to Tenant B's branch is blocked
        $this->actingAs($branch1Mgr)
            ->post('/context/switch-branch', ['branch_id' => $branchB1->id])
            ->assertForbidden();
    }

    public function test_cross_branch_isolation_blocks_branch1_manager_from_branch2(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);

        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $branchA2 = $ctx->runWithoutTenantScope(fn () => Branch::query()
            ->where('tenant_id', $tenantA->id)
            ->where('code', 'RYD-NORTH')
            ->firstOrFail());

        $branch1Mgr = User::query()->where('email', 'branch1@alnoor.local')->firstOrFail();

        $this->actingAs($branch1Mgr)
            ->post('/context/switch-branch', ['branch_id' => $branchA2->id])
            ->assertForbidden();

        $this->actingAs($branch1Mgr)
            ->get('/organization/branches')
            ->assertOk()
            ->assertSee('RYD-MAIN')
            ->assertDontSee('RYD-NORTH');

        $ctx->bind($tenantA, TenantMembership::query()->where('tenant_id', $tenantA->id)->where('user_id', $branch1Mgr->id)->firstOrFail());

        Livewire::actingAs($branch1Mgr)
            ->test(BranchesManager::class)
            ->call('toggleBranchStatus', $branchA2->id)
            ->assertForbidden();
    }

    public function test_platform_admin_boundary_blocks_regular_tenant_owner(): void
    {
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $platformAdmin = User::query()->where('email', 'admin@platform.local')->firstOrFail();

        $this->actingAs($owner)
            ->get('/platform/tenants')
            ->assertForbidden();

        $this->actingAs($platformAdmin)
            ->get('/platform/tenants')
            ->assertOk()
            ->assertSee('al-noor-trading');
    }

    public function test_deactivated_user_is_immediately_locked_out_and_session_invalidated(): void
    {
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();

        $this->actingAs($observer)
            ->get('/dashboard')
            ->assertOk();

        // Deactivate user mid-session
        $observer->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();

        // Disabled user cannot log in
        $this->post('/login', [
            'email' => 'disabled@alnoor.local',
            'password' => 'Demo@Phase03!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_observer_role_is_blocked_from_team_and_branch_mutations(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $tenantA = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $observer = User::query()->where('email', 'observer@alnoor.local')->firstOrFail();

        $this->actingAs($observer)
            ->get('/team')
            ->assertForbidden();

        $ctx->bind($tenantA, TenantMembership::query()->where('tenant_id', $tenantA->id)->where('user_id', $observer->id)->firstOrFail());

        Livewire::actingAs($observer)
            ->test(BranchesManager::class)
            ->call('openCreateBranch')
            ->assertForbidden();
    }

    public function test_ed25519_license_service_validates_signed_and_rejects_tampered_or_expired_licenses(): void
    {
        /** @var Ed25519LicenseService $service */
        $service = app(Ed25519LicenseService::class);
        $keys = $service->generateKeyPair();

        $validPayload = [
            'license_key_id' => 'LIC-TEST-001',
            'edition' => 'self_hosted_pro',
            'max_branches' => 10,
            'expires_at' => now()->addMonths(6)->utc()->toIso8601String(),
        ];

        $signature = $service->signPayload($validPayload, $keys['secret_key']);

        $okResult = $service->verify($validPayload, $signature, $keys['public_key']);
        $this->assertTrue($okResult['valid']);
        $this->assertNull($okResult['reason']);

        // Tampered payload fails
        $tamperedPayload = $validPayload;
        $tamperedPayload['max_branches'] = 999;
        $tamperedResult = $service->verify($tamperedPayload, $signature, $keys['public_key']);
        $this->assertFalse($tamperedResult['valid']);

        // Expired license fails even with valid signature
        $expiredPayload = [
            'license_key_id' => 'LIC-TEST-EXP',
            'edition' => 'self_hosted_pro',
            'expires_at' => now()->subDay()->utc()->toIso8601String(),
        ];
        $expiredSig = $service->signPayload($expiredPayload, $keys['secret_key']);
        $expiredRecord = License::query()->create([
            'license_key_id' => 'LIC-TEST-EXP',
            'edition' => 'self_hosted_pro',
            'payload_json' => $expiredPayload,
            'signature' => $expiredSig,
            'status' => 'active',
            'issued_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $expiredResult = $service->verifyLicenseRecord($expiredRecord, $keys['public_key']);
        $this->assertFalse($expiredResult['valid']);
        $this->assertSame('expired', $expiredRecord->fresh()->status);
    }

    public function test_deployment_profiles_support_saas_switching_and_self_hosted_single_tenant_binding(): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $owner = User::query()->where('email', 'owner@demo.local')->firstOrFail();
        $tenantB = $ctx->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        // SaaS mode allows switching between Tenant A and Tenant B
        config()->set('platform.deployment_mode', 'saas');
        $this->actingAs($owner)
            ->post('/context/switch-tenant', ['tenant_id' => $tenantB->id])
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('JED-MAIN');

        // Self-hosted mode auto-binds primary tenant (Tenant A) and blocks tenant switching
        config()->set('platform.deployment_mode', 'self_hosted');
        $this->actingAs($owner)
            ->post('/context/switch-tenant', ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('RYD-MAIN');
    }
}
