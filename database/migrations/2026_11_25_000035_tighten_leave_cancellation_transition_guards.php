<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Cancellation transition integrity is verified only for the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_requests_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_requests_state_transition BEFORE UPDATE ON leave_requests FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
DECLARE withdrawal_count INT DEFAULT 0;
DECLARE cancellation_count INT DEFAULT 0;
IF NOT (NEW.request_key <=> OLD.request_key) OR NOT (NEW.submission_key <=> OLD.submission_key) OR NOT (NEW.payload_hash <=> OLD.payload_hash) OR NOT (NEW.employee_id <=> OLD.employee_id) OR NOT (NEW.requester_user_id <=> OLD.requester_user_id) OR NOT (NEW.leave_type <=> OLD.leave_type) OR NOT (NEW.created_at <=> OLD.created_at) OR NEW.current_revision <> OLD.current_revision OR (OLD.attendance_review_required = 1 AND NEW.attendance_review_required = 0) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave request identity and submitted revision are immutable';
END IF;
IF OLD.status = 'WAITING_UNIT_MANAGER_SUPPORT' AND NEW.status IN ('WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL') AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'WAITING_GENERAL_MANAGER_SUPPORT' AND NEW.status = 'WAITING_SECRETARY_FINAL' AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status IN ('WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status IN ('APPROVED', 'REJECTED') AND NEW.current_step_order IS NULL AND (NEW.attendance_review_required = OLD.attendance_review_required OR (OLD.attendance_review_required = 0 AND NEW.status = 'REJECTED' AND NEW.attendance_review_required = 1)) THEN
SET transition_valid = TRUE;
ELSEIF OLD.leave_type = 'AL' AND OLD.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status = 'CANCELLED' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO withdrawal_count FROM leave_request_withdrawals AS w INNER JOIN leave_request_revisions AS r ON r.id = w.revision_id WHERE w.request_id = OLD.id AND r.revision = OLD.current_revision AND w.actor_id = OLD.requester_user_id;
IF withdrawal_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'APPROVED' AND NEW.status = 'WAITING_CANCELLATION' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a WHERE a.request_id = OLD.id AND a.revision_id = (SELECT id FROM leave_request_revisions WHERE request_id = OLD.id AND revision = OLD.current_revision) AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'REQUESTED' AND NOT EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS d WHERE d.request_id = a.request_id AND d.cycle = a.cycle AND d.action_code IN ('CONFIRMED', 'REJECTED'));
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'WAITING_CANCELLATION' AND NEW.status = 'APPROVED' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'REJECTED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS r WHERE r.request_id = a.request_id AND r.revision_id = a.revision_id AND r.cycle = a.cycle AND r.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'WAITING_CANCELLATION' AND NEW.status = 'IN_PROCESS_CANCELLATION' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a INNER JOIN leave_request_revisions AS r ON r.id = a.revision_id INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'CONFIRMED' AND b.status = 'CANCELLED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'IN_PROCESS_CANCELLATION' AND NEW.status = 'CANCELLED' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a INNER JOIN leave_request_revisions AS r ON r.id = a.revision_id INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'CONFIRMED' AND b.status = 'CANCELLED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave request workflow transition is invalid'; END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservations_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_reservations_state_transition BEFORE UPDATE ON leave_reservations FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
DECLARE cancellation_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.history_marker_id <=> NEW.history_marker_id) OR NOT (OLD.request_key <=> NEW.request_key) OR NOT (OLD.revision <=> NEW.revision) OR NOT (OLD.leave_year <=> NEW.leave_year) OR NOT (OLD.annual_account_id <=> NEW.annual_account_id) OR NOT (OLD.tabung_account_id <=> NEW.tabung_account_id) OR NOT (OLD.requested_units <=> NEW.requested_units) OR NOT (OLD.annual_units <=> NEW.annual_units) OR NOT (OLD.tabung_units <=> NEW.tabung_units) OR NOT (OLD.tabung_source_year <=> NEW.tabung_source_year) OR NOT (OLD.created_by <=> NEW.created_by) OR NOT (OLD.created_at <=> NEW.created_at) OR NEW.active_request_key IS NOT NULL OR NEW.resolved_at IS NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservation identity and allocation are immutable';
END IF;
IF OLD.status = 'ACTIVE' AND NEW.status IN ('COMMITTED', 'RELEASED') THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'COMMITTED' AND NEW.status = 'CANCELLED' THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_revisions AS r INNER JOIN leave_request_cancellation_actions AS a ON a.revision_id = r.id AND a.request_id = r.request_id INNER JOIN leave_requests AS q ON q.id = r.request_id WHERE r.reservation_id = OLD.id AND r.revision = q.current_revision AND q.status = 'WAITING_CANCELLATION' AND a.action_code = 'CONFIRMED' AND a.cycle = (SELECT MAX(cycle) FROM leave_request_cancellation_actions WHERE request_id = q.id) AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one valid reservation terminal transition is allowed'; END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS leave_active_slots_state_transition');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_active_slots_state_transition BEFORE UPDATE ON leave_active_slots FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
DECLARE cancellation_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.reservation_id <=> NEW.reservation_id) OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.leave_date <=> NEW.leave_date) OR NOT (OLD.session_code <=> NEW.session_code) OR NOT (OLD.created_at <=> NEW.created_at) OR NEW.resolved_at IS NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot identity is immutable';
END IF;
IF OLD.status = 'ACTIVE' AND NEW.status IN ('COMMITTED', 'RELEASED') AND ((NEW.status = 'COMMITTED' AND NEW.active_slot_key = 1) OR (NEW.status = 'RELEASED' AND NEW.active_slot_key IS NULL)) THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'COMMITTED' AND NEW.status = 'RELEASED' AND NEW.active_slot_key IS NULL THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_revisions AS r INNER JOIN leave_request_cancellation_actions AS a ON a.revision_id = r.id AND a.request_id = r.request_id INNER JOIN leave_requests AS q ON q.id = r.request_id WHERE r.reservation_id = OLD.reservation_id AND r.revision = q.current_revision AND q.status = 'WAITING_CANCELLATION' AND a.action_code = 'CONFIRMED' AND a.cycle = (SELECT MAX(cycle) FROM leave_request_cancellation_actions WHERE request_id = q.id) AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one valid leave slot transition is allowed'; END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('The cancellation transition guard strengthening is intentionally not reversible.');
    }
};
