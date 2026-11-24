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
            'code' => 'hr.accounts.link', 'name' => 'hr.accounts.link', 'guard_name' => 'web',
            'label' => 'Link employee accounts', 'description' => 'Manage local development employee account associations.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permission and assignments for administrative review.
    }
};
