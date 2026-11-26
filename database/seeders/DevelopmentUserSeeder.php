<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new RuntimeException('Development user seeding is restricted to local kutmb_hr_dev.');
        }

        $this->call(DevelopmentRoleSeeder::class);

        $created = DB::transaction(function (): array {
            if (DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('IAM baseline is missing.');
            }

            // Never replace or pre-empt the owner's first protected administrator.
            $adminRole = Role::query()->where('code', 'system-admin')->firstOrFail();
            if (! $adminRole->users()->where('status', 'ACTIVE')->whereNotNull('username')->exists()) {
                throw new RuntimeException('Register the first System Admin through /register before seeding development users.');
            }

            $created = [];
            foreach (config('iam.development_role_defaults', []) as $code => $definition) {
                $role = Role::query()->where('code', $code)->firstOrFail();

                if ($role->is_system || $role->guard_name !== 'web' || $role->name !== $code) {
                    throw new RuntimeException('Unexpected development role: '.$code);
                }

                $username = 'dev-'.$code;
                $existing = User::query()->where('username_canonical', $username)->first();

                if ($existing !== null) {
                    if (! $existing->hasRole($role)) {
                        throw new RuntimeException('Existing account has a different role: '.$username);
                    }

                    // Preserve password, status, MFA and all existing assignments.
                    continue;
                }

                $user = User::query()->create([
                    'name' => $definition['label'].' (Development)',
                    'username' => $username,
                    'status' => 'ACTIVE',
                    // Deliberately public synthetic fixture; never use in production.
                    // User's hashed cast stores a password hash, not plaintext.
                    'password' => 'password',
                ]);

                $user->assignRole($role);

                AuditEvent::query()->create([
                    'occurred_at' => now(),
                    'actor_id' => null,
                    'action_code' => 'iam.development_user.seeded',
                    'target_ref' => 'user:'.$user->getKey(),
                    'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'development_seeder', 'changed_fields' => ['account', 'role']],
                ]);

                $created[] = $username;
            }

            return $created;
        }, 3);

        $this->command?->info('Created '.count($created).' development accounts; existing accounts were preserved.');
    }
}
