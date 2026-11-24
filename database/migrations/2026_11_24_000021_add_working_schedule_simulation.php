<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policy_versions', function (Blueprint $table): void {
            $table->timestamp('schedule_simulated_at')->nullable();
            $table->foreignId('schedule_simulated_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('leave_working_schedule_guard', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('leave_working_schedule_guard')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_working_schedule_guard');
        Schema::table('leave_policy_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('schedule_simulated_by');
            $table->dropColumn('schedule_simulated_at');
        });
    }
};
