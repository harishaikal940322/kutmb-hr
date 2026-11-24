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
        foreach (['view' => 'View employees', 'manage' => 'Manage employees'] as $action => $label) {
            $code = 'hr.employees.'.$action;
            DB::table('permissions')->insertOrIgnore([
                'code' => $code, 'name' => $code, 'guard_name' => 'web', 'label' => $label,
                'description' => 'Local development employee foundation.', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain permissions and assignments for explicit administrative review.
    }
};
