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
            throw new RuntimeException('Special cancellation guards require MySQL.');
        }
        Schema::table('leave_request_partial_cancellations', function (Blueprint $table): void {
            $table->string('case_kind', 16)->default('ORDINARY');
            $table->string('evidence_reference', 120)->nullable();
        });
        DB::statement("ALTER TABLE leave_request_partial_cancellations ADD CONSTRAINT lrpc_case_kind_valid CHECK ((case_kind = 'ORDINARY' AND evidence_reference IS NULL) OR (case_kind = 'AFTER_END' AND evidence_reference IS NOT NULL AND TRIM(evidence_reference) <> ''))");
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_partial_cancellations_before_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_before_insert BEFORE INSERT ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
IF NEW.status <> 'REQUESTED' OR NEW.actual_return_session IS NOT NULL OR NEW.actual_return_date IS NOT NULL OR NEW.unused_half_day_units IS NOT NULL OR NEW.hr_verified_by IS NOT NULL OR NEW.general_manager_user_id IS NOT NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A partial leave cancellation must begin with an authorized request';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN employees AS e ON e.id = q.employee_id INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND e.user_id = q.requester_user_id AND ((NEW.case_kind = 'ORDINARY' AND q.requester_user_id = NEW.requested_by) OR (NEW.case_kind = 'AFTER_END' AND e.user_id <> NEW.requested_by AND EXISTS (SELECT 1 FROM users WHERE id = NEW.requested_by AND status = 'ACTIVE' AND username IS NOT NULL))) AND b.status = 'COMMITTED' AND b.requested_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation requires a matching authorized requester and committed approved leave'; END IF;
END
SQL);
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_partial_cancellations_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_state_transition BEFORE UPDATE ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
DECLARE matching_unused_slots INT DEFAULT 0;
DECLARE assignment_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.case_kind <=> NEW.case_kind) OR NOT (OLD.evidence_reference <=> NEW.evidence_reference) OR NOT (OLD.request_id <=> NEW.request_id) OR NOT (OLD.revision_id <=> NEW.revision_id) OR NOT (OLD.cycle <=> NEW.cycle) OR NOT (OLD.requested_by <=> NEW.requested_by) OR NOT (OLD.request_reason <=> NEW.request_reason) OR NOT (OLD.request_operation_key <=> NEW.request_operation_key) OR NOT (OLD.request_payload_hash <=> NEW.request_payload_hash) OR NOT (OLD.created_at <=> NEW.created_at) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation identity and employee request are immutable';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND b.status = 'COMMITTED' AND b.requested_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The approved annual leave and committed reservation must remain current during partial cancellation';
ELSEIF OLD.status = 'REQUESTED' AND NEW.status = 'HR_VERIFIED' AND OLD.actual_return_date IS NULL AND NEW.actual_return_date IS NOT NULL AND NEW.actual_return_session IN ('AM', 'PM') AND NEW.unused_half_day_units > 0 AND NEW.hr_verified_by IS NOT NULL AND NEW.hr_reason IS NOT NULL AND TRIM(NEW.hr_reason) <> '' AND NEW.hr_operation_key IS NOT NULL AND NEW.hr_payload_hash IS NOT NULL AND NEW.verified_at IS NOT NULL AND NEW.general_manager_user_id IS NULL AND NEW.general_manager_assignment_id IS NULL AND NEW.decision_operation_key IS NULL AND NEW.decision_payload_hash IS NULL AND NEW.decided_at IS NULL THEN
SELECT COUNT(*) INTO matching_unused_slots FROM leave_request_days WHERE revision_id = NEW.revision_id AND (leave_date > NEW.actual_return_date OR (leave_date = NEW.actual_return_date AND (NEW.actual_return_session = 'AM' OR session_code = 'PM')));
IF matching_unused_slots <> NEW.unused_half_day_units OR (NEW.case_kind = 'AFTER_END' AND NEW.hr_verified_by = NEW.requested_by) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified unused units must match requested sessions from the verified return session';
END IF;
ELSEIF OLD.status = 'HR_VERIFIED' AND NEW.status IN ('CONFIRMED', 'REJECTED') AND NEW.actual_return_date <=> OLD.actual_return_date AND NEW.actual_return_session <=> OLD.actual_return_session AND NEW.unused_half_day_units <=> OLD.unused_half_day_units AND NEW.hr_verified_by <=> OLD.hr_verified_by AND NEW.hr_reason <=> OLD.hr_reason AND NEW.hr_operation_key <=> OLD.hr_operation_key AND NEW.hr_payload_hash <=> OLD.hr_payload_hash AND NEW.verified_at <=> OLD.verified_at AND NEW.general_manager_user_id IS NOT NULL AND NEW.general_manager_assignment_id IS NOT NULL AND NEW.decision_operation_key IS NOT NULL AND NEW.decision_payload_hash IS NOT NULL AND NEW.decided_at IS NOT NULL THEN
SELECT COUNT(*) INTO assignment_count FROM approval_assignments WHERE id = NEW.general_manager_assignment_id AND user_id = NEW.general_manager_user_id AND office_code = 'PB';
IF assignment_count <> 1 OR NEW.general_manager_user_id = NEW.requested_by OR (NEW.case_kind = 'AFTER_END' AND NEW.general_manager_user_id = NEW.hr_verified_by) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation decisions require a distinct General Manager assignment';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation state transition is invalid';
END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Special cancellation history is forward-only.');
    }
};
