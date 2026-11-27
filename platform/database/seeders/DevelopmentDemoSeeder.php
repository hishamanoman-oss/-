<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Alerts\Models\NotificationRule;
use App\Domain\Analytics\Models\PerformanceTarget;
use App\Domain\Customers\Models\Customer;
use App\Domain\Finance\Services\FinancialMetricsService;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantMembership;
use App\Domain\Identity\Services\RbacCatalog;
use App\Domain\Integrations\Models\DataSource;
use App\Domain\Integrations\Models\FieldMapping;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderFinancialService;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\UserBranchAccess;
use App\Domain\Organizations\Models\Warehouse;
use App\Domain\Reports\Models\ScheduledReport;
use App\Domain\Sales\Models\Category;
use App\Domain\Sales\Models\Product;
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

    public function run(
        TenantContext $tenantContext,
        Ed25519LicenseService $licenseService,
        InventoryLedgerService $inventoryLedger,
        OrderFinancialService $orderService,
        FinancialMetricsService $metricsService,
    ): void {
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

        // 11. Phase 04 Canonical Commerce, Inventory Ledger & Orders Seed (strictly tenant-scoped)
        $tenantA = $tenantContext->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'al-noor-trading')->firstOrFail());
        $tenantB = $tenantContext->runWithoutTenantScope(fn () => Tenant::query()->where('slug', 'horizon-retail')->firstOrFail());

        $this->seedTenantACommerce($tenantContext, $tenantA, $inventoryLedger, $orderService, $metricsService);
        $this->seedTenantBCommerce($tenantContext, $tenantB, $inventoryLedger, $orderService, $metricsService);

        $tenantContext->clear();
    }

    private function seedTenantACommerce(
        TenantContext $tenantContext,
        Tenant $tenantA,
        InventoryLedgerService $inventoryLedger,
        OrderFinancialService $orderService,
        FinancialMetricsService $metricsService,
    ): void {
        $tenantContext->bind($tenantA);

        $branchA1 = Branch::query()->where('code', 'RYD-MAIN')->firstOrFail();
        $branchA2 = Branch::query()->where('code', 'RYD-NORTH')->firstOrFail();
        $wh1 = Warehouse::query()->where('code', 'WH-RYD-01')->firstOrFail();
        $wh2 = Warehouse::query()->where('code', 'WH-RYD-02')->firstOrFail();

        $catHw = Category::query()->updateOrCreate(['code' => 'CAT-HW'], ['name' => 'أجهزة نقاط البيع', 'status' => 'active']);
        $catSvc = Category::query()->updateOrCreate(['code' => 'CAT-SVC'], ['name' => 'الخدمات والتركيب', 'status' => 'active']);

        $p1 = Product::query()->updateOrCreate(
            ['sku' => 'AN-PRN-80'],
            ['category_id' => $catHw->id, 'name' => 'طابعة فواتير حرارية 80مم', 'status' => 'active', 'base_price' => '480.0000', 'cost_price' => '310.0000', 'currency_code' => 'SAR', 'reorder_point' => '10.0000']
        );
        $p2 = Product::query()->updateOrCreate(
            ['sku' => 'AN-SCN-2D'],
            ['category_id' => $catHw->id, 'name' => 'قارئ باركود لاسلكي 2D', 'status' => 'active', 'base_price' => '320.0000', 'cost_price' => '195.0000', 'currency_code' => 'SAR', 'reorder_point' => '8.0000']
        );
        $p3 = Product::query()->updateOrCreate(
            ['sku' => 'AN-TOUCH-15'],
            ['category_id' => $catHw->id, 'name' => 'شاشة كاشير لمس 15.6 بوصة', 'status' => 'active', 'base_price' => '1650.0000', 'cost_price' => '1180.0000', 'currency_code' => 'SAR', 'reorder_point' => '5.0000']
        );
        // Product intentionally missing cost_price to visibly trigger Net Profit Completeness Guard
        $p4 = Product::query()->updateOrCreate(
            ['sku' => 'AN-SVC-INST'],
            ['category_id' => $catSvc->id, 'name' => 'خدمة تركيب وتهيئة ميدانية', 'status' => 'active', 'base_price' => '450.0000', 'cost_price' => null, 'currency_code' => 'SAR', 'reorder_point' => '0.0000']
        );

        $inventoryLedger->recordOpeningOrReceipt($wh1->id, $p1->id, 40, 'seed:alnoor:wh1:p1', 'opening', notes: 'رصيد افتتاحي الرياض');
        $inventoryLedger->recordOpeningOrReceipt($wh1->id, $p2->id, 25, 'seed:alnoor:wh1:p2', 'opening', notes: 'رصيد افتتاحي الرياض');
        $inventoryLedger->recordOpeningOrReceipt($wh1->id, $p3->id, 12, 'seed:alnoor:wh1:p3', 'opening', notes: 'رصيد افتتاحي الرياض');
        $inventoryLedger->recordOpeningOrReceipt($wh2->id, $p1->id, 15, 'seed:alnoor:wh2:p1', 'opening', notes: 'رصيد افتتاحي شمال الرياض');
        $inventoryLedger->recordOpeningOrReceipt($wh2->id, $p2->id, 6, 'seed:alnoor:wh2:p2', 'opening', notes: 'رصيد افتتاحي شمال الرياض');

        $cust1 = Customer::query()->updateOrCreate(
            ['code' => 'CUST-AN-001'],
            ['branch_id' => $branchA1->id, 'name' => 'شركة التقدم التجارية', 'phone' => '+966501000101', 'email' => 'orders@taqaddum.sa', 'status' => 'active']
        );
        $cust2 = Customer::query()->updateOrCreate(
            ['code' => 'CUST-AN-002'],
            ['branch_id' => $branchA2->id, 'name' => 'سالم محمد العتيبي', 'phone' => '+966501000102', 'email' => 'salem@example.sa', 'status' => 'active']
        );

        if (Order::query()->count() === 0) {
            // Order 1: Pending order reserving stock on WH-RYD-01 (Reserved = 3, On Hand unchanged)
            $order1 = $orderService->createOrder(
                branchId: $branchA1->id,
                warehouseId: $wh1->id,
                customerId: $cust1->id,
                orderStatus: 'pending',
                lines: [
                    ['product_id' => $p1->id, 'quantity' => 3, 'discount_amount' => '40.0000', 'tax_amount' => '210.0000'],
                    ['product_id' => $p2->id, 'quantity' => 2, 'discount_amount' => '0.0000', 'tax_amount' => '96.0000'],
                ],
                reserveStock: true,
            );
            $orderService->recordPayment($order1, '1000.0000', 'bank_transfer', 'PAY-AN-001', 'seed:pay:an1');

            // Order 2: Branch 2 order (hidden from Branch 1 manager!) with missing cost service product
            $order2 = $orderService->createOrder(
                branchId: $branchA2->id,
                warehouseId: $wh2->id,
                customerId: $cust2->id,
                orderStatus: 'confirmed',
                lines: [
                    ['product_id' => $p1->id, 'quantity' => 2, 'discount_amount' => '0.0000', 'tax_amount' => '144.0000'],
                    ['product_id' => $p4->id, 'quantity' => 1, 'discount_amount' => '0.0000', 'tax_amount' => '67.5000'],
                ],
                reserveStock: false,
            );
            $orderService->recordPayment($order2, '1621.5000', 'mada', 'PAY-AN-002', 'seed:pay:an2');

            // Order 3: Completed & partially refunded with inventory restock
            $order3 = $orderService->createOrder(
                branchId: $branchA1->id,
                warehouseId: $wh1->id,
                customerId: $cust1->id,
                orderStatus: 'completed',
                lines: [
                    ['product_id' => $p3->id, 'quantity' => 1, 'discount_amount' => '50.0000', 'tax_amount' => '240.0000'],
                ],
                reserveStock: false,
            );
            $pay3 = $orderService->recordPayment($order3, '1840.0000', 'card', 'PAY-AN-003', 'seed:pay:an3');
            $orderService->recordRefund($order3, '200.0000', 'تسوية خصم بعد الفاتورة', 'seed:ref:an3', restockInventory: false, paymentId: $pay3->id);

            $metricsService->computeAndStoreSnapshot(now(), null, null, 'SAR');
        }

        // Phase 05A: Pre-seed Mock Local POS DataSource + Canonical Field Mappings for Tenant A
        $posSource = DataSource::query()->firstOrCreate(
            ['name' => 'Mock Local POS (MySQL)'],
            [
                'connector_type' => 'local_mysql_agent',
                'status' => 'active',
                'freshness_status' => 'disconnected',
                'capture_mode' => 'incremental_polling',
                'sync_interval_seconds' => 30,
                'capabilities_json' => [
                    'supports_read' => true,
                    'supports_write' => true,
                    'supports_polling' => true,
                    'supports_cdc' => true,
                    'supports_webhooks' => true,
                    'allowed_write_commands' => [
                        'product.price.update',
                        'order.status.update',
                        'inventory.stock.adjust',
                    ],
                ],
                'config_encrypted' => [
                    'connection_profile' => 'mock_pos',
                    'webhook_secret' => 'demo-pos-webhook-secret-alnoor',
                ],
            ]
        );

        $this->seedDefaultPosMappings($posSource);

        // Phase 08: Default Alert Rules, Performance Targets & Sample Scheduled Report for Tenant A
        NotificationRule::query()->firstOrCreate(
            ['name' => 'تنبيه انخفاض المخزون الحرج'],
            [
                'rule_type' => 'low_stock',
                'threshold_json' => ['min_items' => 1],
                'channel_in_app' => true,
                'channel_email' => false,
                'recipient_roles_json' => ['tenant_owner', 'general_manager', 'inventory_manager'],
                'is_active' => true,
            ]
        );

        NotificationRule::query()->firstOrCreate(
            ['name' => 'مراقبة تعثر مزامنة نقاط البيع'],
            [
                'rule_type' => 'sync_failure',
                'threshold_json' => ['min_items' => 1],
                'channel_in_app' => true,
                'channel_email' => false,
                'recipient_roles_json' => ['tenant_owner', 'general_manager'],
                'is_active' => true,
            ]
        );

        NotificationRule::query()->firstOrCreate(
            ['name' => 'أوامر عن بُعد بانتظار الاعتماد'],
            [
                'rule_type' => 'command_approval_needed',
                'threshold_json' => ['min_items' => 1],
                'channel_in_app' => true,
                'channel_email' => false,
                'recipient_roles_json' => ['tenant_owner', 'general_manager'],
                'is_active' => true,
            ]
        );

        $owner = User::query()->where('email', 'owner@demo.local')->first();
        PerformanceTarget::query()->firstOrCreate(
            [
                'metric_type' => 'net_sales',
                'period_type' => 'monthly',
                'branch_id' => null,
            ],
            [
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'target_value' => '50000.0000',
                'currency_code' => 'SAR',
                'created_by_user_id' => $owner?->id,
            ]
        );

        ScheduledReport::query()->firstOrCreate(
            ['name' => 'ملخص المبيعات الأسبوعي (آلي)'],
            [
                'report_type' => 'sales_summary',
                'frequency' => 'weekly',
                'format' => 'xlsx',
                'recipients_json' => ['owner@demo.local'],
                'filters_json' => ['period' => '7d'],
                'is_active' => true,
                'created_by_user_id' => $owner?->id,
                'next_run_at' => now()->addDays(7),
            ]
        );
    }

    private function seedDefaultPosMappings(DataSource $source): void
    {
        $rows = [
            ['branch', 'tbl_branches', 'br_id', 'external_id', null, true],
            ['branch', 'tbl_branches', 'br_name', 'name', 'trim', true],
            ['branch', 'tbl_branches', 'br_city', 'city', 'trim', false],

            ['warehouse', 'tbl_warehouses', 'wh_id', 'external_id', null, true],
            ['warehouse', 'tbl_warehouses', 'br_id', 'branch_external_id', null, true],
            ['warehouse', 'tbl_warehouses', 'wh_name', 'name', 'trim', true],

            ['customer', 'tbl_clients', 'client_no', 'external_id', null, true],
            ['customer', 'tbl_clients', 'full_name', 'name', 'trim', true],
            ['customer', 'tbl_clients', 'mobile_no', 'phone', 'trim', false],
            ['customer', 'tbl_clients', 'email_addr', 'email', 'trim', false],
            ['customer', 'tbl_clients', 'updated_at', 'source_updated_at', 'iso_date', false],

            ['product', 'tbl_items', 'item_code', 'external_id', null, true],
            ['product', 'tbl_items', 'item_sku', 'sku', 'uppercase', true],
            ['product', 'tbl_items', 'item_title', 'name', 'trim', true],
            ['product', 'tbl_items', 'cat_name', 'category_name', 'trim', false],
            ['product', 'tbl_items', 'unit_price', 'base_price', 'decimal', true],
            ['product', 'tbl_items', 'cost_price', 'cost_price', 'decimal', false],
            ['product', 'tbl_items', 'is_active', 'status', 'map_status', false],
            ['product', 'tbl_items', 'updated_at', 'source_updated_at', 'iso_date', false],

            ['inventory_balance', 'tbl_stock_qty', 'wh_id', 'warehouse_external_id', null, true],
            ['inventory_balance', 'tbl_stock_qty', 'item_code', 'product_external_id', null, true],
            ['inventory_balance', 'tbl_stock_qty', 'qty_on_hand', 'on_hand', 'decimal', true],
            ['inventory_balance', 'tbl_stock_qty', 'qty_reserved', 'reserved', 'decimal', false],

            ['order', 'tbl_invoices', 'inv_id', 'external_id', null, true],
            ['order', 'tbl_invoices', 'inv_number', 'order_number', 'trim', true],
            ['order', 'tbl_invoices', 'br_id', 'branch_external_id', null, true],
            ['order', 'tbl_invoices', 'client_no', 'customer_external_id', null, false],
            ['order', 'tbl_invoices', 'inv_status', 'status', 'map_status', false],
            ['order', 'tbl_invoices', 'gross_amt', 'gross_sales', 'decimal', true],
            ['order', 'tbl_invoices', 'disc_amt', 'discount_total', 'decimal', false],
            ['order', 'tbl_invoices', 'tax_amt', 'tax_total', 'decimal', false],
            ['order', 'tbl_invoices', 'paid_amt', 'paid_total', 'decimal', false],
            ['order', 'tbl_invoices', 'currency_code', 'currency_code', 'uppercase', false],
            ['order', 'tbl_invoices', 'created_at', 'placed_at', 'iso_date', true],
            ['order', 'tbl_invoices', 'updated_at', 'source_updated_at', 'iso_date', false],

            ['order_item', 'tbl_invoice_lines', 'line_id', 'external_id', null, false],
            ['order_item', 'tbl_invoice_lines', 'item_code', 'product_external_id', null, true],
            ['order_item', 'tbl_invoice_lines', 'qty', 'quantity', 'decimal', true],
            ['order_item', 'tbl_invoice_lines', 'unit_price', 'unit_price', 'decimal', true],
            ['order_item', 'tbl_invoice_lines', 'line_total', 'line_total', 'decimal', false],
        ];

        foreach ($rows as [$entity, $table, $col, $field, $rule, $required]) {
            FieldMapping::query()->updateOrCreate(
                [
                    'data_source_id' => $source->id,
                    'canonical_entity' => $entity,
                    'canonical_field' => $field,
                ],
                [
                    'source_table' => $table,
                    'source_column' => $col,
                    'transform_rule' => $rule,
                    'is_required' => $required,
                ]
            );
        }
    }

    private function seedTenantBCommerce(
        TenantContext $tenantContext,
        Tenant $tenantB,
        InventoryLedgerService $inventoryLedger,
        OrderFinancialService $orderService,
        FinancialMetricsService $metricsService,
    ): void {
        $tenantContext->bind($tenantB);

        $branchB1 = Branch::query()->where('code', 'JED-MAIN')->firstOrFail();
        $whB1 = Warehouse::query()->where('code', 'WH-JED-01')->firstOrFail();

        $catRetail = Category::query()->updateOrCreate(['code' => 'CAT-JED'], ['name' => 'Horizon Hardware', 'status' => 'active']);
        $pB1 = Product::query()->updateOrCreate(
            ['sku' => 'HZ-POS-PRO'],
            ['category_id' => $catRetail->id, 'name' => 'Horizon Smart Terminal Pro', 'status' => 'active', 'base_price' => '950.0000', 'cost_price' => '550.0000', 'currency_code' => 'SAR', 'reorder_point' => '5.0000']
        );

        $inventoryLedger->recordOpeningOrReceipt($whB1->id, $pB1->id, 18, 'seed:horizon:whb1:p1', 'opening', notes: 'Jeddah initial stock');

        $custB = Customer::query()->updateOrCreate(
            ['code' => 'CUST-HZ-001'],
            ['branch_id' => $branchB1->id, 'name' => 'Red Sea Hypermarket', 'phone' => '+966502000201', 'email' => 'ap@redsea.sa', 'status' => 'active']
        );

        if (Order::query()->count() === 0) {
            $orderB1 = $orderService->createOrder(
                branchId: $branchB1->id,
                warehouseId: $whB1->id,
                customerId: $custB->id,
                orderStatus: 'completed',
                lines: [
                    ['product_id' => $pB1->id, 'quantity' => 2, 'discount_amount' => '100.0000', 'tax_amount' => '270.0000'],
                ],
                reserveStock: false,
            );
            $orderService->recordPayment($orderB1, '2070.0000', 'card', 'PAY-HZ-001', 'seed:pay:hz1');

            $metricsService->computeAndStoreSnapshot(now(), null, '150.0000', 'SAR');
        }
    }
}
