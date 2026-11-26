<?php

namespace App\Console\Commands;

use App\Models\Role;
use Database\Seeders\DevelopmentEmployeeOrganizationSeeder;
use Database\Seeders\DevelopmentLeaveAccessSeeder;
use Database\Seeders\DevelopmentLeaveConfigurationSeeder;
use Database\Seeders\DevelopmentRoleSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedLocalDevelopmentData extends Command
{
    protected $signature = 'dev:data';

    protected $description = 'Seed all synthetic development roles, users, employee mappings and leave configuration';

    public function handle(): int
    {
        $config = config('database.connections.mysql');
        if (! app()->environment('local') || app()->runningUnitTests()
            || app()->configurationIsCached() || config('database.default') !== 'mysql'
            || ! is_array($config) || ($config['driver'] ?? null) !== 'mysql'
            || ($config['host'] ?? null) !== 'mysql' || (string) ($config['port'] ?? '') !== '3306'
            || ($config['database'] ?? null) !== 'kutmb_hr_dev'
            || ! empty($config['url']) || ! empty($config['unix_socket'])
            || isset($config['read']) || isset($config['write'])) {
            $this->error('Development data seeding requires uncached local Docker MySQL configuration for kutmb_hr_dev.');

            return self::FAILURE;
        }

        $connection = DB::connection('mysql');
        if ($connection->getDatabaseName() !== 'kutmb_hr_dev'
            || $connection->selectOne('SELECT DATABASE() AS db_name')?->db_name !== 'kutmb_hr_dev'
            || $connection->transactionLevel() !== 0) {
            $this->error('Unexpected database identity or transaction state. No seeding performed.');

            return self::FAILURE;
        }

        $migrator = app('migrator');
        $migrator->setConnection('mysql');
        $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
        if (! $migrator->repositoryExists()
            || array_diff(array_keys($migrationFiles), $migrator->getRepository()->getRan()) !== []) {
            $this->error('Apply pending migrations using php artisan migrate before running dev:data.');

            return self::FAILURE;
        }

        $admin = Role::query()->where('code', 'system-admin')->where('is_system', true)
            ->where('name', 'system-admin')->where('guard_name', 'web')->first();
        if ($admin === null || ! $admin->users()->where('status', 'ACTIVE')->whereNotNull('username')->exists()) {
            $this->error('Register the first protected System Admin through the existing local /register flow before running dev:data.');

            return self::FAILURE;
        }

        $seeders = [
            DevelopmentRoleSeeder::class,
            DevelopmentUserSeeder::class,
            DevelopmentLeaveAccessSeeder::class,
            DevelopmentEmployeeOrganizationSeeder::class,
            DevelopmentLeaveConfigurationSeeder::class,
        ];

        $this->info('Seeding synthetic development data on kutmb_hr_dev. No database reset or migration will run.');
        $this->warn('Existing development fixture permissions may be restored by the leave access seeder. Existing accounts and posted balances are preserved according to the individual seeder rules.');

        foreach ($seeders as $seeder) {
            $this->line('Running '.class_basename($seeder));
            $status = $this->call('db:seed', ['--database' => 'mysql', '--class' => $seeder]);
            if ($status !== self::SUCCESS) {
                $this->error('Stopped at '.class_basename($seeder).'. Earlier seeders may have committed. Resolve the error before rerunning dev:data.');

                return self::FAILURE;
            }
        }

        $this->info('Development data seeding completed.');

        return self::SUCCESS;
    }
}
