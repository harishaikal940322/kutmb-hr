<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_active_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained('leave_reservations')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('leave_date');
            $table->string('session_code', 2);
            $table->unsignedTinyInteger('active_slot_key')->nullable()->default(1);
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->unique(['employee_id', 'leave_date', 'session_code', 'active_slot_key'], 'las_employee_date_session_uq');
            $table->unique(['reservation_id', 'leave_date', 'session_code'], 'las_reservation_date_session_uq');
            $table->index(['reservation_id', 'status'], 'las_reservation_status_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_active_slots ADD CONSTRAINT las_session_valid CHECK (session_code IN ('AM', 'PM'))");
            DB::statement("ALTER TABLE leave_active_slots ADD CONSTRAINT las_state_valid CHECK ((status = 'ACTIVE' AND active_slot_key = 1 AND resolved_at IS NULL) OR (status = 'COMMITTED' AND active_slot_key = 1 AND resolved_at IS NOT NULL) OR (status = 'RELEASED' AND active_slot_key IS NULL AND resolved_at IS NOT NULL))");
            DB::unprepared("CREATE TRIGGER leave_active_slots_no_delete BEFORE DELETE ON leave_active_slots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot history cannot be deleted'");
            DB::unprepared("CREATE TRIGGER leave_active_slots_state_transition BEFORE UPDATE ON leave_active_slots FOR EACH ROW BEGIN IF OLD.status <> 'ACTIVE' OR NEW.status NOT IN ('COMMITTED', 'RELEASED') OR NOT (OLD.id <=> NEW.id) OR NOT (OLD.reservation_id <=> NEW.reservation_id) OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.leave_date <=> NEW.leave_date) OR NOT (OLD.session_code <=> NEW.session_code) OR NOT (OLD.created_at <=> NEW.created_at) OR NEW.resolved_at IS NULL OR (NEW.status = 'COMMITTED' AND NEW.active_slot_key <> 1) OR (NEW.status = 'RELEASED' AND NEW.active_slot_key IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one terminal leave slot transition is allowed'; END IF; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS leave_active_slots_state_transition');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_active_slots_no_delete');
        }

        Schema::dropIfExists('leave_active_slots');
    }
};
