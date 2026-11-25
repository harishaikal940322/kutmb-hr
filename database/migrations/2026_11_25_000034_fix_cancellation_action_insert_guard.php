<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Cancellation action integrity is verified only for the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_cancellation_actions_before_insert');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_cancellation_actions_before_insert BEFORE INSERT ON leave_request_cancellation_actions FOR EACH ROW BEGIN
DECLARE current_revision_value INT UNSIGNED DEFAULT NULL;
DECLARE current_status_value VARCHAR(40) DEFAULT NULL;
DECLARE current_type_value VARCHAR(12) DEFAULT NULL;
DECLARE requester_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE linked_user_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE assignment_user_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE assignment_office VARCHAR(32) DEFAULT NULL;
SELECT r.current_revision, r.status, r.leave_type, r.requester_user_id, e.user_id
INTO current_revision_value, current_status_value, current_type_value, requester_id, linked_user_id
FROM leave_requests AS r INNER JOIN employees AS e ON e.id = r.employee_id WHERE r.id = NEW.request_id FOR UPDATE;
IF current_revision_value IS NULL OR NEW.revision_id NOT IN (SELECT id FROM leave_request_revisions WHERE request_id = NEW.request_id AND revision = current_revision_value) OR current_type_value <> 'AL' THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cancellation actions require the current Annual Leave request revision';
END IF;
IF NEW.action_code = 'REQUESTED' THEN
IF current_status_value <> 'APPROVED' OR NEW.actor_id <> requester_id OR NEW.actor_id <> linked_user_id OR NEW.assignment_id IS NOT NULL OR NEW.cycle < 1 THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the linked employee can request cancellation of an approved Annual Leave request';
END IF;
ELSE
SELECT user_id, office_code INTO assignment_user_id, assignment_office FROM approval_assignments WHERE id = NEW.assignment_id;
IF current_status_value <> 'WAITING_CANCELLATION' OR NEW.actor_id = requester_id OR NEW.cycle < 1 OR assignment_user_id <> NEW.actor_id OR assignment_office <> 'PB' THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A cancellation decision requires a General Manager assignment and pending employee request';
END IF;
END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('The MySQL cancellation action guard correction is intentionally not reversible.');
    }
};
