<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Identity\Services\RbacCatalog;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Tenants\Models\License;
use App\Domain\Tenants\Models\Tenant;
use App\Domain\Tenants\Services\Ed25519LicenseService;
use App\Domain\Tenants\Services\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class DevelopmentDemoSeeder extends Seeder
{
    private const DEV_PASSWORD = 'Demo@Phase03!';

    public function run(TenantContext $tenantContext, Ed25519LicenseService $licenseService): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentDemoSeeder may only run in local or testing environments.');
        }

        RbacCatalog::seed();

        $tenantContext->runWithoutTenantScope(function () use ($licenseService): void {
            $roles = Role::query()->whereNull('tenant_id')->get()->keyBy('slug');
            $passwordHash = Hash::make(self::DEV_PASSWORD);

            // 1. Platform Super Admin (NO tenant membership required)
            User::query()->updateOrCreate(
                ['email' => 'admin@platform.local'],
                [
                    'name' => 'Platform Super Admin',
                    'password' => $passwordHash,
                    'is_platform_admin' => true,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );

            // 2. Tenant A: Al-Noor Trading
            $tenantA = Tenant::query()->updateOrCreate(
                ['slug' => 'al-noor-trading'],
                [
                    'name' => 'شركة النور التجارية (Al-Noor Trading)',
                    'status' => 'active',
                    'default_currency' => 'SAR',
                    'default_timezone' => 'Asia/Riyadh',
                    'default_locale' => 'ar',
                ]
            );

            $branchA1 = Branch::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'code' => 'RYD-MAIN'],
                ['name' => 'الفرع الرئيسي - الرياض (Main Branch)', 'city' => 'الرياض', 'status' => 'active', 'timezone' => 'Asia/Riyadh']
            );
            $branchA2 = Branch::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'code' => 'RYD-NORTH'],
                ['name' => 'فرع شمال الرياض (North Branch)', 'city' => 'الرياض', 'status' => 'active', 'timezone' => 'Asia/Riyadh']
            );

            Warehouse::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'code' => 'WH-RYD-01'],
                ['branch_id' => $branchA1->id, 'name' => 'مستودع الرياض المركزي', 'is_default' => true, 'status' => 'active']
            );
            Warehouse::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'code' => 'WH-RYD-02'],
                ['branch_id' => $branchA2->id, 'name' => 'مستودع شمال الرياض', 'is_default' => false, 'status' => 'active']
            );

            // 3. Tenant B: Horizon Retail
            $tenantB = Tenant::query()->updateOrCreate(
                ['slug' => 'horizon-retail'],
                [
                    'name' => 'آفاق للتجزئة (Horizon Retail)',
                    'status' => 'active',
                    'default_currency' => 'SAR',
                    'default_timezone' => 'Asia/Riyadh',
                    'default_locale' => 'ar',
                ]
            );

            $branchB1 = Branch::query()->updateOrCreate(
                ['tenant_id' => $tenantB->id, 'code' => 'JED-MAIN'],
                ['name' => 'فرع جدة الرئيسي (Jeddah Flagship)', 'city' => 'جدة', 'status' => 'active', 'timezone' => 'Asia/Riyadh']
            );

            Warehouse::query()->updateOrCreate(
                ['tenant_id' => $tenantB->id, 'code' => 'WH-JED-01'],
                ['branch_id' => $branchB1->id, 'name' => 'مستودع جدة الرئيسي', 'is_default' => true, 'status' => 'active']
            );

            // 4. Multi-Tenant Owner (Owner in BOTH Tenant A and Tenant B)
            $owner = User::query()->updateOrCreate(
                ['email' => 'owner@demo.local'],
                [
                    'name' => 'سليمان المالك (Tenant Owner)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );

            $ownerMemA = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $owner->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $ownerMemA->roles()->sync([$roles['tenant_owner']->id]);

            $ownerMemB = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantB->id, 'user_id' => $owner->id],
                ['status' => 'active', 'is_default' => false, 'joined_at' => now()]
            );
            $ownerMemB->roles()->sync([$roles['tenant_owner']->id]);

            // 5. Branch Manager (Scoped ONLY to Branch 1 of Tenant A)
            $branchMgr = User::query()->updateOrCreate(
                ['email' => 'branch1@alnoor.local'],
                [
                    'name' => 'فهد مدير الفرع الأول (Branch 1 Manager)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );
            $branchMgrMem = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $branchMgr->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $branchMgrMem->roles()->sync([$roles['branch_manager']->id]);
            UserBranchAccess::query()->where('tenant_membership_id', $branchMgrMem->id)->delete();
            UserBranchAccess::query()->create([
                'tenant_membership_id' => $branchMgrMem->id,
                'branch_id' => $branchA1->id,
                'access_level' => 'manage',
            ]);

            // 6. Accountant (Tenant A, view access to both branches)
            $accountant = User::query()->updateOrCreate(
                ['email' => 'accountant@alnoor.local'],
                [
                    'name' => 'نورة المحاسبة (Accountant)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );
            $acctMem = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $accountant->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $acctMem->roles()->sync([$roles['accountant']->id]);
            UserBranchAccess::query()->where('tenant_membership_id', $acctMem->id)->delete();
            foreach ([$branchA1->id, $branchA2->id] as $bId) {
                UserBranchAccess::query()->create([
                    'tenant_membership_id' => $acctMem->id,
                    'branch_id' => $bId,
                    'access_level' => 'view',
                ]);
            }

            // 7. Inventory Manager (Tenant A, manage access to both branches)
            $invMgr = User::query()->updateOrCreate(
                ['email' => 'inventory@alnoor.local'],
                [
                    'name' => 'خالد مدير المخزون (Inventory Manager)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );
            $invMem = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $invMgr->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $invMem->roles()->sync([$roles['inventory_manager']->id]);
            UserBranchAccess::query()->where('tenant_membership_id', $invMem->id)->delete();
            foreach ([$branchA1->id, $branchA2->id] as $bId) {
                UserBranchAccess::query()->create([
                    'tenant_membership_id' => $invMem->id,
                    'branch_id' => $bId,
                    'access_level' => 'manage',
                ]);
            }

            // 8. Observer (Tenant A, view access to Branch 1)
            $observer = User::query()->updateOrCreate(
                ['email' => 'observer@alnoor.local'],
                [
                    'name' => 'طارق المراقب (Observer)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => true,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );
            $obsMem = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $observer->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $obsMem->roles()->sync([$roles['observer']->id]);
            UserBranchAccess::query()->where('tenant_membership_id', $obsMem->id)->delete();
            UserBranchAccess::query()->create([
                'tenant_membership_id' => $obsMem->id,
                'branch_id' => $branchA1->id,
                'access_level' => 'view',
            ]);

            // 9. Deactivated User (Tenant A)
            $disabled = User::query()->updateOrCreate(
                ['email' => 'disabled@alnoor.local'],
                [
                    'name' => 'مستخدم معطل (Disabled User)',
                    'password' => $passwordHash,
                    'is_platform_admin' => false,
                    'is_active' => false,
                    'preferred_locale' => 'ar',
                    'preferred_theme' => 'light',
                ]
            );
            $disMem = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantA->id, 'user_id' => $disabled->id],
                ['status' => 'active', 'is_default' => true, 'joined_at' => now()]
            );
            $disMem->roles()->sync([$roles['observer']->id]);

            // 10. Sample Signed Ed25519 Self-Hosted License Record
            if (License::query()->where('license_key_id', 'LIC-DEMO-2026-001')->doesntExist()) {
                $keys = $licenseService->generateKeyPair();
                $issuedAt = now()->utc();
                $expiresAt = $issuedAt->addYear();
                $payload = [
                    'license_key_id' => 'LIC-DEMO-2026-001',
                    'edition' => 'enterprise_self_hosted',
                    'tenant_slug' => $tenantA->slug,
                    'max_branches' => 25,
                    'issued_at' => $issuedAt->toIso8601String(),
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
                $sig = $licenseService->signPayload($payload, $keys['secret_key']);

                License::query()->create([
                    'tenant_id' => $tenantA->id,
                    'license_key_id' => 'LIC-DEMO-2026-001',
                    'edition' => 'enterprise_self_hosted',
                    'payload_json' => $payload,
                    'signature' => $sig,
                    'status' => 'active',
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                    'verified_at' => now(),
                ]);
            }
        });
    }
}
