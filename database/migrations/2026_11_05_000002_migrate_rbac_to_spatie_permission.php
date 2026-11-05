<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->renameColumn('name', 'label');
            $table->string('name', 80)->after('code');
            $table->string('guard_name', 80)->default('web')->after('name');
            $table->boolean('is_system')->default(false)->after('label');
        });
        DB::table('roles')->update(['name' => DB::raw('code')]);
        DB::table('roles')->where('code', 'system-admin')->update(['is_system' => true]);
        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->renameColumn('name', 'label');
            $table->string('name', 100)->after('code');
            $table->string('guard_name', 80)->default('web')->after('name');
            $table->text('description')->nullable()->after('label');
        });
        DB::table('permissions')->update(['name' => DB::raw('code')]);
        Schema::table('permissions', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::create('menu_permission_assignments', function (Blueprint $table): void {
            $table->string('menu_key', 100)->primary();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->timestamps();
        });

        DB::table('user_roles')->orderBy('user_id')->chunk(500, function ($assignments): void {
            DB::table('model_has_roles')->insertOrIgnore($assignments->map(fn (object $assignment): array => [
                'role_id' => $assignment->role_id,
                'model_type' => User::class,
                'model_id' => $assignment->user_id,
            ])->all());
        });

        DB::table('role_permissions')->orderBy('role_id')->chunk(500, function ($assignments): void {
            DB::table('role_has_permissions')->insertOrIgnore($assignments->map(fn (object $assignment): array => [
                'role_id' => $assignment->role_id,
                'permission_id' => $assignment->permission_id,
            ])->all());
        });

        Schema::rename('user_roles', 'legacy_user_roles');
        Schema::rename('role_permissions', 'legacy_role_permissions');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->orderBy('model_id')
            ->chunk(500, function ($assignments): void {
                DB::table('legacy_user_roles')->insertOrIgnore($assignments->map(fn (object $assignment): array => [
                    'user_id' => $assignment->model_id,
                    'role_id' => $assignment->role_id,
                ])->all());
            });

        DB::table('role_has_permissions')->orderBy('role_id')->chunk(500, function ($assignments): void {
            DB::table('legacy_role_permissions')->insertOrIgnore($assignments->map(fn (object $assignment): array => [
                'role_id' => $assignment->role_id,
                'permission_id' => $assignment->permission_id,
            ])->all());
        });

        Schema::dropIfExists('menu_permission_assignments');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->dropColumn(['name', 'guard_name', 'is_system']);
            $table->renameColumn('label', 'name');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique('permissions_name_guard_name_unique');
            $table->dropColumn(['name', 'guard_name', 'description']);
            $table->renameColumn('label', 'name');
        });

        Schema::rename('legacy_user_roles', 'user_roles');
        Schema::rename('legacy_role_permissions', 'role_permissions');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
