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
            'code' => 'hr.assignments.manage', 'name' => 'hr.assignments.manage', 'guard_name' => 'web',
            'label' => 'Manage assignments', 'description' => 'Manage local development employee assignments.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permission and assignments for administrative review.
    }
};
