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
            'code' => 'hr.organization.manage',
            'name' => 'hr.organization.manage',
            'guard_name' => 'web',
            'label' => 'Manage organization references',
            'description' => 'Manage local development units and employment categories.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain the permission and any assignments for explicit administrative review.
    }
};
