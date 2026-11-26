<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class DevelopmentRoleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new RuntimeException('Development role seeding requires the existing local kutmb_hr_dev MySQL database.');
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            if (DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('IAM baseline is missing. Apply the approved existing migrations first.');
            }

            $catalog = app(PermissionCatalog::class);
            $definitions = $catalog->definitions();
            $defaults = config('iam.development_role_defaults', []);
            $assignable = $catalog->assignableCodes();

            foreach ($defaults as $code => $definition) {
                if ($code === 'system-admin' || array_diff($definition['permissions'], $assignable) !== []) {
                    throw new RuntimeException('Invalid development role definition: '.$code);
                }
            }

            $permissions = [];
            foreach ($definitions as $code => $definition) {
                $permission = Permission::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $code,
                        'guard_name' => 'web',
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                    ],
                );

                if ($permission->name !== $code || $permission->guard_name !== 'web') {
                    throw new RuntimeException('Unexpected existing permission identity: '.$code);
                }

                $permissions[$code] = $permission;
            }

            $admin = Role::query()->firstOrCreate(
                ['code' => 'system-admin'],
                ['name' => 'system-admin', 'guard_name' => 'web', 'label' => 'System Admin', 'is_system' => true],
            );

            if (! $admin->is_system || $admin->name !== 'system-admin' || $admin->guard_name !== 'web') {
                throw new RuntimeException('Unexpected existing System Admin role identity.');
            }

            // Restore only the original protected IAM management capability.
            $admin->givePermissionTo($permissions['iam.rbac.manage']);

            foreach ($defaults as $code => $definition) {
                $role = Role::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $code, 'guard_name' => 'web', 'label' => $definition['label'], 'is_system' => false],
                );

                if ($role->is_system || $role->name !== $code || $role->guard_name !== 'web') {
                    throw new RuntimeException('Unexpected existing role identity: '.$code);
                }

                // An administrator's existing role edits must survive repeated seeding.
                if (! $role->wasRecentlyCreated) {
                    continue;
                }

                foreach ($definition['permissions'] as $permissionCode) {
                    $role->givePermissionTo($permissions[$permissionCode]);
                }
            }
        }, 3);

        $registrar->forgetCachedPermissions();
    }
}
