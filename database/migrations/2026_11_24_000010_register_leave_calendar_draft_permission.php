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
            'code' => 'leave.calendar.manage', 'name' => 'leave.calendar.manage', 'guard_name' => 'web',
            'label' => 'Manage draft leave calendars', 'description' => 'Manage local development leave calendar drafts.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permission and assignments for administrative review.
    }
};
