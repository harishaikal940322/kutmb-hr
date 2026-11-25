<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'code' => 'leave.calendar.team.view',
            'name' => 'leave.calendar.team.view',
            'guard_name' => 'web',
            'label' => 'View approved unit absence names and sessions',
            'description' => 'Local/testing-only read boundary for a synthetic approved-only unit calendar.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('The team-calendar permission catalogue entry is intentionally retained.');
    }
};
