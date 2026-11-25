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
            'code' => 'leave.entitlement.view',
            'name' => 'leave.entitlement.view',
            'guard_name' => 'web',
            'label' => 'Lihat profil dan kelayakan cuti',
            'description' => 'View minimum employee profile and confirmed annual entitlement in local development.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permission and assignments for administrative review.
    }
};
