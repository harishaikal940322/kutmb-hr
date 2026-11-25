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
            throw new RuntimeException('Pending leave withdrawal integrity is verified only for the selected MySQL database.');
        }

        Schema::create('leave_request_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('leave_requests')->restrictOnDelete();
            $table->unsignedBigInteger('revision_id');
            $table->foreign(['revision_id', 'request_id'], 'lrw_revision_request_fk')
                ->references(['id', 'request_id'])->on('leave_request_revisions')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation_key', 80)->unique();
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['request_id', 'revision_id'], 'lrw_request_revision_uq');
        });

        DB::statement('ALTER TABLE leave_requests DROP CHECK lrq_state_valid');
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT lrq_state_valid CHECK ((status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND current_step_order IS NOT NULL) OR (status IN ('APPROVED', 'REJECTED', 'CANCELLED') AND current_step_order IS NULL))");
        DB::unprepared('DROP TRIGGER IF EXISTS leave_requests_state_transition');
        DB::unprepared($this->requestTransitionTrigger(true));
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_withdrawals_before_insert BEFORE INSERT ON leave_request_withdrawals FOR EACH ROW BEGIN
DECLARE current_revision_value INT UNSIGNED DEFAULT NULL;
DECLARE current_status_value VARCHAR(40) DEFAULT NULL;
DECLARE current_requester_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE revision_value INT UNSIGNED DEFAULT NULL;
SELECT current_revision, status, requester_user_id INTO current_revision_value, current_status_value, current_requester_id FROM leave_requests WHERE id = NEW.request_id FOR UPDATE;
SELECT revision INTO revision_value FROM leave_request_revisions WHERE id = NEW.revision_id AND request_id = NEW.request_id;
IF revision_value IS NULL OR revision_value <> current_revision_value OR current_requester_id <> NEW.actor_id OR current_status_value NOT IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A withdrawal must be made by the linked employee for the current pending revision';
END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER leave_request_withdrawals_no_update BEFORE UPDATE ON leave_request_withdrawals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave withdrawal history is immutable'");
        DB::unprepared("CREATE TRIGGER leave_request_withdrawals_no_delete BEFORE DELETE ON leave_request_withdrawals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave withdrawal history cannot be deleted'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Pending leave withdrawal integrity is verified only for the selected MySQL database.');
        }
        if (DB::table('leave_requests')->where('status', 'CANCELLED')->exists()) {
            throw new RuntimeException('The leave request state constraint cannot be rolled back after a request has been cancelled.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_withdrawals_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_withdrawals_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_request_withdrawals_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_requests_state_transition');
        DB::statement('ALTER TABLE leave_requests DROP CHECK lrq_state_valid');
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT lrq_state_valid CHECK ((status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND current_step_order IS NOT NULL) OR (status IN ('APPROVED', 'REJECTED') AND current_step_order IS NULL))");
        DB::unprepared($this->requestTransitionTrigger(false));
        Schema::dropIfExists('leave_request_withdrawals');
    }

    /** @return literal-string */
    private function requestTransitionTrigger(bool $allowWithdrawal): string
    {
        $withdrawalBranch = $allowWithdrawal ? <<<'SQL'
ELSEIF OLD.leave_type = 'AL' AND OLD.status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status = 'CANCELLED' AND NEW.current_step_order IS NULL AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SELECT COUNT(*) INTO withdrawal_count FROM leave_request_withdrawals AS w INNER JOIN leave_request_revisions AS r ON r.id = w.revision_id WHERE w.request_id = OLD.id AND r.revision = OLD.current_revision AND w.actor_id = OLD.requester_user_id;
IF withdrawal_count = 1 THEN
SET transition_valid = TRUE;
END IF;
SQL : '';
        $withdrawalDeclaration = $allowWithdrawal ? 'DECLARE withdrawal_count INT DEFAULT 0;' : '';
        $body = <<<SQL
CREATE TRIGGER leave_requests_state_transition BEFORE UPDATE ON leave_requests FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
$withdrawalDeclaration
IF NOT (NEW.request_key <=> OLD.request_key) OR NOT (NEW.submission_key <=> OLD.submission_key) OR NOT (NEW.payload_hash <=> OLD.payload_hash) OR NOT (NEW.employee_id <=> OLD.employee_id) OR NOT (NEW.requester_user_id <=> OLD.requester_user_id) OR NOT (NEW.leave_type <=> OLD.leave_type) OR NOT (NEW.created_at <=> OLD.created_at) OR NEW.current_revision <> OLD.current_revision OR (OLD.attendance_review_required = 1 AND NEW.attendance_review_required = 0) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave request identity and submitted revision are immutable';
END IF;
IF OLD.status = 'WAITING_UNIT_MANAGER_SUPPORT' AND NEW.status IN ('WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL') AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'WAITING_GENERAL_MANAGER_SUPPORT' AND NEW.status = 'WAITING_SECRETARY_FINAL' AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status IN ('WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status IN ('APPROVED', 'REJECTED') AND NEW.current_step_order IS NULL AND (NEW.attendance_review_required = OLD.attendance_review_required OR (OLD.attendance_review_required = 0 AND NEW.status = 'REJECTED' AND NEW.attendance_review_required = 1)) THEN
SET transition_valid = TRUE;
$withdrawalBranch
END IF;
IF transition_valid = FALSE THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave request workflow transition is invalid';
END IF;
END
SQL;

        return $body;
    }
};
