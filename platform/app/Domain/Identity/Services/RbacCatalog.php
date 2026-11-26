<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;

final class RbacCatalog
{
    /**
     * @return list<array{key: string, module: string, label_en: string, label_ar: string}>
     */
    public static function permissions(): array
    {
        return [
            ['key' => 'dashboard.view', 'module' => 'dashboard', 'label_en' => 'View Dashboard', 'label_ar' => 'عرض لوحة المتابعة'],
            ['key' => 'branches.view', 'module' => 'organization', 'label_en' => 'View Branches & Warehouses', 'label_ar' => 'عرض الفروع والمستودعات'],
            ['key' => 'branches.manage', 'module' => 'organization', 'label_en' => 'Manage Branches', 'label_ar' => 'إدارة الفروع'],
            ['key' => 'warehouses.manage', 'module' => 'organization', 'label_en' => 'Manage Warehouses', 'label_ar' => 'إدارة المستودعات'],
            ['key' => 'team.view', 'module' => 'team', 'label_en' => 'View Team Members', 'label_ar' => 'عرض أعضاء الفريق'],
            ['key' => 'team.manage', 'module' => 'team', 'label_en' => 'Manage Team & Roles', 'label_ar' => 'إدارة الفريق والصلاحيات'],
            ['key' => 'sales.view', 'module' => 'sales', 'label_en' => 'View Sales & Orders', 'label_ar' => 'عرض المبيعات والطلبات'],
            ['key' => 'inventory.view', 'module' => 'inventory', 'label_en' => 'View Inventory', 'label_ar' => 'عرض المخزون'],
            ['key' => 'inventory.adjust', 'module' => 'inventory', 'label_en' => 'Request Stock Adjustments', 'label_ar' => 'طلب تسوية المخزون'],
            ['key' => 'integrations.view', 'module' => 'integrations', 'label_en' => 'View Integrations', 'label_ar' => 'عرض التكاملات'],
            ['key' => 'integrations.manage', 'module' => 'integrations', 'label_en' => 'Manage Integrations', 'label_ar' => 'إدارة التكاملات'],
            ['key' => 'commands.request', 'module' => 'commands', 'label_en' => 'Request Remote Commands', 'label_ar' => 'طلب أوامر التحكم عن بُعد'],
            ['key' => 'commands.approve', 'module' => 'commands', 'label_en' => 'Approve Remote Commands', 'label_ar' => 'اعتماد أوامر التحكم عن بُعد'],
            ['key' => 'reports.view', 'module' => 'reports', 'label_en' => 'View Financial Reports', 'label_ar' => 'عرض التقارير المالية'],
            ['key' => 'audit.view', 'module' => 'governance', 'label_en' => 'View Audit Logs', 'label_ar' => 'عرض سجل التدقيق'],
            ['key' => 'settings.manage', 'module' => 'settings', 'label_en' => 'Manage Tenant Settings', 'label_ar' => 'إدارة إعدادات المنشأة'],
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        $allKeys = array_column(self::permissions(), 'key');

        return [
            'tenant_owner' => [
                'name' => 'Tenant Owner',
                'description' => 'Full ownership across all tenant branches, team, integrations, and governance.',
                'permissions' => $allKeys,
            ],
            'general_manager' => [
                'name' => 'General Manager',
                'description' => 'Cross-branch operational oversight and management across the tenant.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'branches.manage', 'warehouses.manage',
                    'team.view', 'sales.view', 'inventory.view', 'inventory.adjust',
                    'integrations.view', 'commands.request', 'commands.approve',
                    'reports.view', 'audit.view',
                ],
            ],
            'branch_manager' => [
                'name' => 'Branch Manager',
                'description' => 'Scoped management of assigned branches and warehouses.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'branches.manage', 'warehouses.manage',
                    'team.view', 'sales.view', 'inventory.view', 'inventory.adjust',
                    'commands.request', 'reports.view',
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Financial reporting, sales review, and audit inspection.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'sales.view', 'inventory.view',
                    'reports.view', 'audit.view',
                ],
            ],
            'inventory_manager' => [
                'name' => 'Inventory Manager',
                'description' => 'Warehouse visibility, stock balances, and inventory adjustment requests.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'warehouses.manage',
                    'inventory.view', 'inventory.adjust', 'commands.request',
                ],
            ],
            'sales_staff' => [
                'name' => 'Sales Staff',
                'description' => 'Sales visibility within assigned branches.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'sales.view', 'inventory.view',
                ],
            ],
            'observer' => [
                'name' => 'Observer',
                'description' => 'Read-only executive visibility without mutation capabilities.',
                'permissions' => [
                    'dashboard.view', 'branches.view', 'sales.view', 'inventory.view', 'reports.view',
                ],
            ],
        ];
    }

    public static function seed(): void
    {
        $permissionIds = [];

        foreach (self::permissions() as $perm) {
            $model = Permission::query()->updateOrCreate(
                ['key' => $perm['key']],
                [
                    'module' => $perm['module'],
                    'label_en' => $perm['label_en'],
                    'label_ar' => $perm['label_ar'],
                ]
            );
            $permissionIds[$perm['key']] = $model->id;
        }

        foreach (self::roles() as $slug => $roleDef) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $slug)->first();
            if ($role === null) {
                $role = Role::query()->create([
                    'tenant_id' => null,
                    'slug' => $slug,
                    'name' => $roleDef['name'],
                    'is_system' => true,
                    'description' => $roleDef['description'],
                ]);
            } else {
                $role->update([
                    'name' => $roleDef['name'],
                    'is_system' => true,
                    'description' => $roleDef['description'],
                ]);
            }

            $syncIds = array_values(array_filter(array_map(
                fn (string $key): ?int => $permissionIds[$key] ?? null,
                $roleDef['permissions']
            )));

            $role->permissions()->sync($syncIds);
        }
    }
}
