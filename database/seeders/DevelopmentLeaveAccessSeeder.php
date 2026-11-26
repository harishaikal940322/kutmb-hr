<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/** Explicit opt-in for the new workflow permissions on existing development roles. */
class DevelopmentLeaveAccessSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new RuntimeException('Leave access seeding requires local kutmb_hr_dev.');
        }

        $this->call(DevelopmentRoleSeeder::class);

        DB::transaction(function (): void {
            if (DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('IAM baseline is missing.');
            }

            $codes = ['leave.request.self', 'leave.request.decide', 'leave.reports.view', 'leave.attendance.review', 'leave.year-end.preview', 'leave.year-end.post', 'leave.request.record-on-behalf', 'leave.adjustment.prepare', 'leave.adjustment.decide'];
            $permissions = Permission::query()->whereIn('code', $codes)->where('guard_name', 'web')->get()->keyBy('code');

            foreach (config('iam.development_role_defaults', []) as $code => $definition) {
                $required = array_intersect($codes, $definition['permissions']);
                if ($required === []) {
                    continue;
                }

                $role = Role::query()->where('code', $code)->lockForUpdate()->firstOrFail();
                if ($role->is_system || $role->name !== $code || $role->guard_name !== 'web') {
                    throw new RuntimeException('Unexpected development role identity: '.$code);
                }

                foreach ($required as $permission) {
                    $role->givePermissionTo($permissions->get($permission) ?? throw new RuntimeException('Missing permission: '.$permission));
                }
            }
        }, 3);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
