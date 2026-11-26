<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owner-operated local reset. Never an automated-test or general migration bypass.
 */
class ResetLocalDevelopmentDatabase extends Command
{
    protected $signature = 'dev:reset-database';

    protected $description = 'Interactively reset only kutmb_hr_dev and seed its baseline roles';

    private static bool $authorized = false;

    public static function allowsDestructiveCommand(?string $command, ?string $connection): bool
    {
        // Only the confirmed invocation of migrate:fresh and its nested db:wipe.
        return self::$authorized
            && $connection === 'mysql'
            && in_array($command, ['migrate:fresh', 'db:wipe'], true)
            && self::isLocalTarget();
    }

    public function handle(): int
    {
        if (! self::isLocalTarget() || ! $this->input->isInteractive()
            || ! defined('STDIN') || ! defined('STDOUT')
            || ! stream_isatty(STDIN) || ! stream_isatty(STDOUT)) {
            $this->error('Reset refused. Use an interactive local Docker terminal for kutmb_hr_dev only. CI, tests, cached configuration and other targets are not permitted.');

            return self::FAILURE;
        }

        $this->assertDatabaseIdentity();
        $this->warn('DESTRUCTIVE: this permanently deletes ALL tables and data in kutmb_hr_dev, including accounts, HR records, balances and audit history.');
        $this->warn('Only continue for synthetic development data. Preserve any required backup and prior incident evidence outside this database. Stop other application usage and jobs first.');
        $this->line('Target: mysql:3306 / kutmb_hr_dev');
        $answer = $this->ask('Type RESET kutmb_hr_dev exactly to confirm');
        if ($answer !== 'RESET kutmb_hr_dev') {
            $this->info('Cancelled. No reset or seeding was performed.');

            return self::FAILURE;
        }

        $this->assertDatabaseIdentity();
        try {
            self::$authorized = true;
            DB::prohibitDestructiveCommands(false);
            $status = $this->call('migrate:fresh', [
                '--database' => 'mysql',
                '--force' => true,
            ]);
        } finally {
            // Never keep the exception enabled for seeders or subsequent commands.
            self::$authorized = false;
            DB::prohibitDestructiveCommands(true);
        }

        if ($status !== self::SUCCESS) {
            $this->error('Migration failed. The reset may be partial. Inspect the error; do not automatically retry.');

            return self::FAILURE;
        }

        $this->assertDatabaseIdentity();
        $status = $this->call('db:seed', ['--database' => 'mysql', '--force' => true]);
        if ($status !== self::SUCCESS) {
            $this->error('Schema was reset but baseline seeding failed. Inspect the error before continuing.');

            return self::FAILURE;
        }

        $this->info('Development schema and baseline roles recreated. Destructive commands are blocked again.');
        $this->line('Register the first protected System Admin using the existing local /register flow before explicitly seeding development users.');
        $this->line('Then run php artisan dev:data to seed all development fixtures.');

        return self::SUCCESS;
    }

    private static function isLocalTarget(): bool
    {
        if (PHP_SAPI !== 'cli' || ! app()->environment('local') || app()->runningUnitTests()
            || app()->configurationIsCached() || config('database.default') !== 'mysql') {
            return false;
        }
        foreach (['CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL', 'TF_BUILD'] as $flag) {
            if (getenv($flag) !== false || array_key_exists($flag, $_ENV) || array_key_exists($flag, $_SERVER)) {
                return false;
            }
        }

        $config = config('database.connections.mysql');

        return is_array($config)
            && ($config['driver'] ?? null) === 'mysql'
            && ($config['host'] ?? null) === 'mysql'
            && (string) ($config['port'] ?? '') === '3306'
            && ($config['database'] ?? null) === 'kutmb_hr_dev'
            && empty($config['url'])
            && empty($config['unix_socket'])
            && ! isset($config['read'])
            && ! isset($config['write']);
    }

    private function assertDatabaseIdentity(): void
    {
        if (! self::isLocalTarget() || DB::connection('mysql')->getDatabaseName() !== 'kutmb_hr_dev'
            || DB::connection('mysql')->selectOne('SELECT DATABASE() AS db_name')?->db_name !== 'kutmb_hr_dev'
            || DB::connection('mysql')->transactionLevel() !== 0) {
            throw new RuntimeException('Reset refused: effective database identity or transaction state is not the expected local development target.');
        }
    }
}
