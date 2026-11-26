<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Annual correction resubmission integrity is verified only for the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_requests_state_transition');
        DB::unprepared($this->trigger(true));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_requests_state_transition');
        DB::unprepared($this->trigger(false));
    }

    private function trigger(bool $allowResubmission): string
    {
        $resubmission = $allowResubmission ? <<<'SQL'
ELSEIF OLD.leave_type = 'AL' AND OLD.status = 'DRAFT' AND OLD.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision + 1 AND NEW.current_step_order = 1 AND NEW.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO correction_count FROM leave_request_revisions AS old_revision
INNER JOIN leave_workflow_actions AS returned ON returned.revision_id = old_revision.id AND returned.action_code = 'RETURN_FOR_CORRECTION' AND returned.reason IS NOT NULL AND TRIM(returned.reason) <> ''
INNER JOIN leave_approval_steps AS returned_step ON returned_step.id = returned.approval_step_id AND returned_step.revision_id = old_revision.id
INNER JOIN leave_reservations AS released ON released.id = old_revision.reservation_id AND released.status = 'RELEASED'
INNER JOIN leave_request_revisions AS new_revision ON new_revision.request_id = OLD.id AND new_revision.revision = NEW.current_revision AND new_revision.created_by = OLD.requester_user_id AND new_revision.requested_units > 0
INNER JOIN leave_reservations AS active ON active.id = new_revision.reservation_id AND active.request_key = OLD.request_key AND active.revision = NEW.current_revision AND active.status = 'ACTIVE' AND active.active_request_key = OLD.request_key
INNER JOIN leave_approval_steps AS first_step ON first_step.revision_id = new_revision.id AND first_step.step_order = 1
INNER JOIN approval_assignments AS assignment ON assignment.id = first_step.assignment_id AND assignment.user_id = first_step.assignee_user_id AND assignment.office_code = first_step.office_code AND assignment.effective_from <= CURRENT_DATE() AND (assignment.effective_to IS NULL OR assignment.effective_to >= CURRENT_DATE())
WHERE old_revision.request_id = OLD.id AND old_revision.revision = OLD.current_revision AND returned_step.step_order = OLD.current_step_order
AND ((NEW.status = 'WAITING_UNIT_MANAGER_SUPPORT' AND first_step.stage_code = 'UNIT_MANAGER_SUPPORT') OR (NEW.status = 'WAITING_GENERAL_MANAGER_SUPPORT' AND first_step.stage_code = 'GENERAL_MANAGER_SUPPORT') OR (NEW.status = 'WAITING_GENERAL_MANAGER_FINAL' AND first_step.stage_code = 'GENERAL_MANAGER_FINAL') OR (NEW.status = 'WAITING_SECRETARY_FINAL' AND first_step.stage_code = 'SECRETARY_FINAL'));
IF correction_count = 1 THEN SET transition_valid = TRUE; END IF;
SQL : '';

        return <<<SQL
CREATE TRIGGER leave_requests_state_transition BEFORE UPDATE ON leave_requests FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
DECLARE withdrawal_count INT DEFAULT 0;
DECLARE cancellation_count INT DEFAULT 0;
DECLARE correction_count INT DEFAULT 0;
IF NOT (NEW.request_key <=> OLD.request_key) OR NOT (NEW.submission_key <=> OLD.submission_key) OR NOT (NEW.payload_hash <=> OLD.payload_hash) OR NOT (NEW.employee_id <=> OLD.employee_id) OR NOT (NEW.requester_user_id <=> OLD.requester_user_id) OR NOT (NEW.leave_type <=> OLD.leave_type) OR NOT (NEW.created_at <=> OLD.created_at) OR (NEW.current_revision <> OLD.current_revision AND NOT (OLD.leave_type = 'AL' AND OLD.status = 'DRAFT' AND OLD.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision + 1 AND NEW.current_step_order = 1 AND NEW.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL'))) OR (OLD.attendance_review_required = 1 AND NEW.attendance_review_required = 0) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave request identity and submitted revision are immutable';
END IF;
IF OLD.status = 'WAITING_UNIT_MANAGER_SUPPORT' AND NEW.status IN ('WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL') AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'WAITING_GENERAL_MANAGER_SUPPORT' AND NEW.status = 'WAITING_SECRETARY_FINAL' AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status IN ('WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status IN ('APPROVED', 'REJECTED') AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND (NEW.attendance_review_required = OLD.attendance_review_required OR (OLD.attendance_review_required = 0 AND NEW.status = 'REJECTED' AND NEW.attendance_review_required = 1)) THEN
SET transition_valid = TRUE;
ELSEIF OLD.leave_type = 'AL' AND OLD.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status = 'CANCELLED' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO withdrawal_count FROM leave_request_withdrawals AS w INNER JOIN leave_request_revisions AS r ON r.id = w.revision_id WHERE w.request_id = OLD.id AND r.revision = OLD.current_revision AND w.actor_id = OLD.requester_user_id;
IF withdrawal_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.leave_type = 'AL' AND OLD.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status = 'DRAFT' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO correction_count FROM leave_workflow_actions AS a INNER JOIN leave_approval_steps AS s ON s.id = a.approval_step_id AND s.revision_id = a.revision_id INNER JOIN leave_request_revisions AS r ON r.id = a.revision_id INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE a.revision_id = (SELECT id FROM leave_request_revisions WHERE request_id = OLD.id AND revision = OLD.current_revision) AND s.step_order = OLD.current_step_order AND a.action_code = 'RETURN_FOR_CORRECTION' AND a.reason IS NOT NULL AND TRIM(a.reason) <> '' AND b.status = 'RELEASED';
IF correction_count = 1 THEN SET transition_valid = TRUE; END IF;
$resubmission
ELSEIF OLD.status = 'APPROVED' AND NEW.status = 'WAITING_CANCELLATION' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a WHERE a.request_id = OLD.id AND a.revision_id = (SELECT id FROM leave_request_revisions WHERE request_id = OLD.id AND revision = OLD.current_revision) AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'REQUESTED' AND NOT EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS d WHERE d.request_id = a.request_id AND d.cycle = a.cycle AND d.action_code IN ('CONFIRMED', 'REJECTED'));
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'WAITING_CANCELLATION' AND NEW.status = 'APPROVED' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'REJECTED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS r WHERE r.request_id = a.request_id AND r.revision_id = a.revision_id AND r.cycle = a.cycle AND r.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'WAITING_CANCELLATION' AND NEW.status = 'IN_PROCESS_CANCELLATION' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a INNER JOIN leave_request_revisions AS r ON r.id = a.revision_id INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'CONFIRMED' AND b.status = 'CANCELLED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
ELSEIF OLD.status = 'IN_PROCESS_CANCELLATION' AND NEW.status = 'CANCELLED' AND NEW.current_step_order IS NULL AND NEW.current_revision = OLD.current_revision AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO cancellation_count FROM leave_request_cancellation_actions AS a INNER JOIN leave_request_revisions AS r ON r.id = a.revision_id INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE a.request_id = OLD.id AND a.cycle = (SELECT COALESCE(MAX(cycle), 0) FROM leave_request_cancellation_actions WHERE request_id = OLD.id) AND a.action_code = 'CONFIRMED' AND b.status = 'CANCELLED' AND EXISTS (SELECT 1 FROM leave_request_cancellation_actions AS requested WHERE requested.request_id = a.request_id AND requested.revision_id = a.revision_id AND requested.cycle = a.cycle AND requested.action_code = 'REQUESTED');
IF cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave request workflow transition is invalid'; END IF;
END
SQL;
    }
};
