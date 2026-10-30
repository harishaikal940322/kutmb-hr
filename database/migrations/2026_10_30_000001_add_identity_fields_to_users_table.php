<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->after('name');
            $table->string('username_canonical', 64)->nullable()->after('username')->collation('ascii_bin')->unique();
            $table->string('status', 16)->default('ACTIVE')->after('password');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot roll back identity fields while users.email contains NULL. Manually remediate every NULL email before retrying; no identity columns or user rows were changed.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username_canonical']);
            $table->dropColumn(['username', 'username_canonical', 'status']);
        });
    }
};
