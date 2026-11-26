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
            throw new RuntimeException('Partial cancellation source guards require MySQL.');
        }
        foreach (['leave_request_partial_cancellations', 'leave_request_partial_cancellation_events'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('actual_return_session', 2)->nullable();
            });
            DB::statement("ALTER TABLE {$table} ADD CHECK (actual_return_session IS NULL OR (actual_return_date IS NOT NULL AND actual_return_session IN ('AM', 'PM')))");
        }
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_partial_cancellations_before_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_before_insert BEFORE INSERT ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
IF NEW.status <> 'REQUESTED' OR NEW.actual_return_session IS NOT NULL OR NEW.actual_return_date IS NOT NULL OR NEW.unused_half_day_units IS NOT NULL OR NEW.hr_verified_by IS NOT NULL OR NEW.general_manager_user_id IS NOT NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A partial leave cancellation must begin with an employee request';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN employees AS e ON e.id = q.employee_id INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND e.user_id = NEW.requested_by AND q.requester_user_id = NEW.requested_by AND b.status = 'COMMITTED' AND b.requested_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation requires the linked employee and a committed approved leave'; END IF;
END
SQL);
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_partial_cancellations_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_state_transition BEFORE UPDATE ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
DECLARE matching_unused_slots INT DEFAULT 0;
DECLARE assignment_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.request_id <=> NEW.request_id) OR NOT (OLD.revision_id <=> NEW.revision_id) OR NOT (OLD.cycle <=> NEW.cycle) OR NOT (OLD.requested_by <=> NEW.requested_by) OR NOT (OLD.request_reason <=> NEW.request_reason) OR NOT (OLD.request_operation_key <=> NEW.request_operation_key) OR NOT (OLD.request_payload_hash <=> NEW.request_payload_hash) OR NOT (OLD.created_at <=> NEW.created_at) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation identity and employee request are immutable';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND b.status = 'COMMITTED' AND b.requested_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The approved annual leave and committed reservation must remain current during partial cancellation';
ELSEIF OLD.status = 'REQUESTED' AND NEW.status = 'HR_VERIFIED' AND OLD.actual_return_date IS NULL AND NEW.actual_return_date IS NOT NULL AND NEW.actual_return_session IN ('AM', 'PM') AND NEW.unused_half_day_units > 0 AND NEW.hr_verified_by IS NOT NULL AND NEW.hr_reason IS NOT NULL AND TRIM(NEW.hr_reason) <> '' AND NEW.hr_operation_key IS NOT NULL AND NEW.hr_payload_hash IS NOT NULL AND NEW.verified_at IS NOT NULL AND NEW.general_manager_user_id IS NULL AND NEW.general_manager_assignment_id IS NULL AND NEW.decision_operation_key IS NULL AND NEW.decision_payload_hash IS NULL AND NEW.decided_at IS NULL THEN
SELECT COUNT(*) INTO matching_unused_slots FROM leave_request_days WHERE revision_id = NEW.revision_id AND (leave_date > NEW.actual_return_date OR (leave_date = NEW.actual_return_date AND (NEW.actual_return_session = 'AM' OR session_code = 'PM')));
IF matching_unused_slots <> NEW.unused_half_day_units THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified unused units must match requested sessions from the verified return session';
END IF;
ELSEIF OLD.status = 'HR_VERIFIED' AND NEW.status IN ('CONFIRMED', 'REJECTED') AND NEW.actual_return_date <=> OLD.actual_return_date AND NEW.actual_return_session <=> OLD.actual_return_session AND NEW.unused_half_day_units <=> OLD.unused_half_day_units AND NEW.hr_verified_by <=> OLD.hr_verified_by AND NEW.hr_reason <=> OLD.hr_reason AND NEW.hr_operation_key <=> OLD.hr_operation_key AND NEW.hr_payload_hash <=> OLD.hr_payload_hash AND NEW.verified_at <=> OLD.verified_at AND NEW.general_manager_user_id IS NOT NULL AND NEW.general_manager_assignment_id IS NOT NULL AND NEW.decision_operation_key IS NOT NULL AND NEW.decision_payload_hash IS NOT NULL AND NEW.decided_at IS NOT NULL THEN
SELECT COUNT(*) INTO assignment_count FROM approval_assignments WHERE id = NEW.general_manager_assignment_id AND user_id = NEW.general_manager_user_id AND office_code = 'PB';
IF assignment_count <> 1 OR NEW.general_manager_user_id = NEW.requested_by THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation decisions require a distinct General Manager assignment';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation state transition is invalid';
END IF;
END
SQL);
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_partial_cancellation_events_before_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellation_events_before_insert BEFORE INSERT ON leave_request_partial_cancellation_events FOR EACH ROW BEGIN
DECLARE matching_count INT DEFAULT 0;
SELECT COUNT(*) INTO matching_count FROM leave_request_partial_cancellations WHERE id = NEW.partial_cancellation_id AND status = NEW.event_code AND ((NEW.event_code = 'REQUESTED' AND requested_by = NEW.actor_id AND request_reason <=> NEW.reason AND request_operation_key = NEW.operation_key AND request_payload_hash = NEW.payload_hash AND NEW.assignment_id IS NULL) OR (NEW.event_code = 'HR_VERIFIED' AND hr_verified_by = NEW.actor_id AND actual_return_date <=> NEW.actual_return_date AND actual_return_session <=> NEW.actual_return_session AND unused_half_day_units <=> NEW.unused_half_day_units AND hr_reason <=> NEW.reason AND hr_operation_key = NEW.operation_key AND hr_payload_hash = NEW.payload_hash AND NEW.assignment_id IS NULL) OR (NEW.event_code IN ('CONFIRMED', 'REJECTED') AND general_manager_user_id = NEW.actor_id AND general_manager_assignment_id = NEW.assignment_id AND decision_reason <=> NEW.reason AND decision_operation_key = NEW.operation_key AND decision_payload_hash = NEW.payload_hash AND NEW.actual_return_date IS NULL AND NEW.actual_return_session IS NULL AND NEW.unused_half_day_units IS NULL));
IF matching_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation event must match its recorded state'; END IF;
END
SQL);
        DB::unprepared('DROP TRIGGER IF EXISTS leave_active_slots_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_active_slots_state_transition BEFORE UPDATE ON leave_active_slots FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
DECLARE cancellation_count INT DEFAULT 0;
DECLARE partial_cancellation_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.reservation_id <=> NEW.reservation_id) OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.leave_date <=> NEW.leave_date) OR NOT (OLD.session_code <=> NEW.session_code) OR NOT (OLD.created_at <=> NEW.created_at) OR NEW.resolved_at IS NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot identity is immutable';
END IF;
IF OLD.status = 'ACTIVE' AND NEW.status IN ('COMMITTED', 'RELEASED') AND ((NEW.status = 'COMMITTED' AND NEW.active_slot_key = 1) OR (NEW.status = 'RELEASED' AND NEW.active_slot_key IS NULL)) THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'COMMITTED' AND NEW.status = 'RELEASED' AND NEW.active_slot_key IS NULL THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_revisions AS r INNER JOIN leave_request_cancellation_actions AS a ON a.revision_id = r.id AND a.request_id = r.request_id INNER JOIN leave_requests AS q ON q.id = r.request_id WHERE r.reservation_id = OLD.reservation_id AND r.revision = q.current_revision AND q.status = 'WAITING_CANCELLATION' AND a.action_code = 'CONFIRMED' AND a.cycle = (SELECT MAX(cycle) FROM leave_request_cancellation_actions WHERE request_id = q.id) AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
SELECT COUNT(*) INTO partial_cancellation_count FROM leave_request_partial_cancellations AS p INNER JOIN leave_request_revisions AS r ON r.id = p.revision_id AND r.request_id = p.request_id INNER JOIN leave_requests AS q ON q.id = p.request_id WHERE r.reservation_id = OLD.reservation_id AND r.revision = q.current_revision AND q.status = 'APPROVED' AND p.status = 'CONFIRMED' AND (OLD.leave_date > p.actual_return_date OR (OLD.leave_date = p.actual_return_date AND (p.actual_return_session = 'AM' OR (p.actual_return_session = 'PM' AND OLD.session_code = 'PM'))));
IF partial_cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one valid leave slot transition is allowed'; END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Partial cancellation session history is forward-only.');
    }
};
