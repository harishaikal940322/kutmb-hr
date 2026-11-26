<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_attendance_review_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained('leave_attendance_reviews')->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('finding', 32);
            $table->text('note');
            $table->string('evidence_reference', 255);
            $table->uuid('operation_key')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_attendance_review_outcomes ADD CONSTRAINT laro_finding_valid CHECK (finding IN ('ABSENCE_CONFIRMED', 'ABSENCE_NOT_CONFIRMED', 'REFERRED'))");
            DB::unprepared("CREATE TRIGGER leave_attendance_review_outcomes_no_update BEFORE UPDATE ON leave_attendance_review_outcomes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Review outcome history is immutable'");
            DB::unprepared("CREATE TRIGGER leave_attendance_review_outcomes_no_delete BEFORE DELETE ON leave_attendance_review_outcomes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Review outcome history is immutable'");
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Attendance review outcome history cannot be rolled back automatically.');
    }
};
