<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Never seed application accounts or production HR role grants.
        if (app()->environment(['local', 'testing']) && config('database.default') === 'mysql'
            && config('database.connections.mysql.database') === 'kutmb_hr_dev') {
            $this->call(DevelopmentRoleSeeder::class);
        }
    }
}
