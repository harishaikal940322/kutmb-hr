<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Partial attendance reconciliation requires MySQL.');
        }
        Schema::create('leave_attendance_non_use_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outcome_id')->constrained('leave_attendance_review_outcomes', 'id', 'lanus_outcome_fk')->restrictOnDelete();
            $table->foreignId('allocation_id')->unique()->constrained('leave_reservation_slot_allocations', 'id', 'lanus_allocation_fk')->restrictOnDelete();
            $table->foreignId('claim_id')->constrained('leave_late_release_claims', 'id', 'lanus_claim_fk')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::unprepared(<<<'SQL'
CREATE TRIGGER lanus_source_guard BEFORE INSERT ON leave_attendance_non_use_slots FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM leave_attendance_review_outcomes AS outcome
        JOIN leave_attendance_reviews AS review ON review.id = outcome.review_id
        JOIN leave_request_revisions AS revision ON revision.id = review.revision_id
        JOIN leave_reservation_slot_allocations AS allocation ON allocation.reservation_id = revision.reservation_id
        JOIN leave_late_release_claims AS claim ON claim.reservation_id = allocation.reservation_id
            AND claim.annual_account_id = allocation.annual_account_id AND claim.source_year = allocation.leave_year
        WHERE outcome.id = NEW.outcome_id AND outcome.finding = 'PARTIAL_NON_USE_CONFIRMED'
            AND review.review_code = 'LATE_AL_REJECTION' AND allocation.id = NEW.allocation_id
            AND allocation.allocation_bucket = 'ANNUAL' AND allocation.units = 1 AND claim.id = NEW.claim_id
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial non-use must match original attendance and annual claim';
    END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER lanus_no_update BEFORE UPDATE ON leave_attendance_non_use_slots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Non-use session history is immutable'");
        DB::unprepared("CREATE TRIGGER lanus_no_delete BEFORE DELETE ON leave_attendance_non_use_slots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Non-use session history cannot be deleted'");
        DB::statement("ALTER TABLE leave_attendance_review_outcomes ADD CONSTRAINT laro_finding_v3 CHECK (finding IN ('ABSENCE_CONFIRMED','ABSENCE_NOT_CONFIRMED','REFERRED','NO_ABSENCE_CONFIRMED','PARTIAL_NON_USE_CONFIRMED'))");
        DB::statement('ALTER TABLE leave_attendance_review_outcomes DROP CHECK laro_finding_v2');
    }

    public function down(): void
    {
        throw new RuntimeException('Partial attendance reconciliation history is forward-only.');
    }
};
