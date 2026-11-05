<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            [
                'code' => 'iam.rbac.manage',
                'label' => 'Manage access control',
                'description' => 'Manage system roles, role permissions, and menu access.',
            ],
            [
                'code' => 'iam.users.view',
                'label' => 'View user directory',
                'description' => 'View the local development user directory preview.',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'name' => $permission['code'],
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                    'guard_name' => 'web',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $systemAdminRoleId = DB::table('roles')->where('code', 'system-admin')->value('id');
        $managePermissionId = DB::table('permissions')->where('code', 'iam.rbac.manage')->value('id');

        if ($systemAdminRoleId !== null && $managePermissionId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $systemAdminRoleId,
                'permission_id' => $managePermissionId,
            ]);
        }

        $menuPermissions = [
            'users.index' => 'iam.users.view',
            'iam.roles.index' => 'iam.rbac.manage',
            'iam.permissions.index' => 'iam.rbac.manage',
            'iam.menus.index' => 'iam.rbac.manage',
        ];

        foreach ($menuPermissions as $menuKey => $permissionCode) {
            $permissionId = DB::table('permissions')->where('code', $permissionCode)->value('id');

            DB::table('menu_permission_assignments')->updateOrInsert(
                ['menu_key' => $menuKey],
                ['permission_id' => $permissionId, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep catalog entries and assignments; the schema migration restores them
        // to the legacy relationships if the package conversion is rolled back.
    }
};
