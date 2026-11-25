<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_employee_history_markers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('request_key', 120);
            $table->unsignedInteger('revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['request_key', 'revision'], 'lehm_request_revision_uq');
            $table->index(['employee_id', 'created_at'], 'lehm_employee_created_idx');
        });

        Schema::create('leave_calendar_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('history_marker_id')->constrained('leave_employee_history_markers')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('leave_date');
            $table->unsignedTinyInteger('leave_units');
            $table->foreignId('calendar_version_id')->constrained('leave_calendar_versions')->restrictOnDelete();
            $table->foreignId('policy_version_id')->constrained('leave_policy_versions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['history_marker_id', 'leave_date'], 'lcus_marker_date_uq');
            $table->index(['leave_date', 'calendar_version_id'], 'lcus_calendar_date_idx');
            $table->index(['leave_date', 'policy_version_id'], 'lcus_policy_date_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leave_calendar_usage_snapshots ADD CONSTRAINT lcus_units_valid CHECK (leave_units IN (1, 2))');
            DB::unprepared("CREATE TRIGGER leave_employee_history_markers_no_update BEFORE UPDATE ON leave_employee_history_markers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave employee history markers are append-only'");
            DB::unprepared("CREATE TRIGGER leave_employee_history_markers_no_delete BEFORE DELETE ON leave_employee_history_markers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave employee history markers are append-only'");
            DB::unprepared("CREATE TRIGGER leave_calendar_usage_snapshots_no_update BEFORE UPDATE ON leave_calendar_usage_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave calendar usage snapshots are append-only'");
            DB::unprepared("CREATE TRIGGER leave_calendar_usage_snapshots_no_delete BEFORE DELETE ON leave_calendar_usage_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave calendar usage snapshots are append-only'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS leave_calendar_usage_snapshots_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_calendar_usage_snapshots_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_employee_history_markers_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_employee_history_markers_no_update');
        }

        Schema::dropIfExists('leave_calendar_usage_snapshots');
        Schema::dropIfExists('leave_employee_history_markers');
    }
};
