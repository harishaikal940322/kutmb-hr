<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_assignment_guard', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('office_assignment_guard')->insert(['id' => 1]);
        if (app()->environment(['local', 'testing'])) {
            DB::table('permissions')->insertOrIgnore([
                'code' => 'hr.offices.manage', 'name' => 'hr.offices.manage', 'guard_name' => 'web',
                'label' => 'Manage approval offices', 'description' => 'Manage local development approval office appointments.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_assignment_guard');
        // Retain permission and assignments for administrative review.
    }
};
