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
            throw new RuntimeException('The internal leave request workflow schema is verified only for the selected MySQL database.');
        }

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_key', 120)->unique();
            $table->string('submission_key', 80)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->string('leave_type', 2);
            $table->string('status', 40);
            $table->unsignedSmallInteger('current_revision')->default(1);
            $table->unsignedTinyInteger('current_step_order')->nullable();
            $table->boolean('attendance_review_required')->default(false);
            $table->timestamps();
            $table->index(['employee_id', 'created_at'], 'lrq_employee_created_idx');
            $table->index(['status', 'current_step_order'], 'lrq_status_step_idx');
        });

        Schema::create('leave_request_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('leave_requests')->restrictOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->unsignedSmallInteger('leave_year');
            $table->unsignedBigInteger('requested_units');
            $table->text('reason')->nullable();
            $table->foreignId('reservation_id')->unique()->constrained('leave_reservations')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['request_id', 'revision'], 'lrrev_request_revision_uq');
            $table->unique(['id', 'request_id'], 'lrrev_id_request_uq');
        });

        Schema::create('leave_request_days', function (Blueprint $table): void {
            $table->foreignId('revision_id')->constrained('leave_request_revisions')->restrictOnDelete();
            $table->date('leave_date');
            $table->string('session_code', 2);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['revision_id', 'leave_date', 'session_code'], 'lrd_revision_date_session_pk');
            $table->index(['leave_date', 'session_code'], 'lrd_date_session_idx');
        });

        Schema::create('leave_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('leave_request_revisions')->restrictOnDelete();
            $table->unsignedTinyInteger('step_order');
            $table->string('stage_code', 40);
            $table->string('office_code', 32);
            $table->foreignId('assignment_id')->constrained('approval_assignments')->restrictOnDelete();
            $table->foreignId('assignee_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->boolean('is_final');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['revision_id', 'step_order'], 'las_revision_step_uq');
            $table->unique(['id', 'revision_id'], 'las_id_revision_uq');
            $table->index(['assignee_user_id', 'created_at'], 'las_assignee_created_idx');
        });

        Schema::create('leave_workflow_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('leave_request_revisions')->restrictOnDelete();
            $table->unsignedBigInteger('approval_step_id');
            $table->foreign(['approval_step_id', 'revision_id'], 'lwa_step_revision_fk')
                ->references(['id', 'revision_id'])->on('leave_approval_steps')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained('approval_assignments')->restrictOnDelete();
            $table->string('action_code', 32);
            $table->text('reason')->nullable();
            $table->string('operation_key', 80)->unique();
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['revision_id', 'approval_step_id'], 'lwa_revision_step_uq');
        });

        Schema::create('leave_attendance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('leave_request_revisions')->restrictOnDelete();
            $table->foreignId('workflow_action_id')->constrained('leave_workflow_actions')->restrictOnDelete();
            $table->string('review_code', 40);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->unique(['revision_id', 'review_code'], 'lar_revision_code_uq');
            $table->unique('workflow_action_id', 'lar_action_uq');
        });

        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT lrq_type_valid CHECK (leave_type IN ('AL', 'EL'))");
        DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT lrq_state_valid CHECK ((status IN ('WAITING_UNIT_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND current_step_order IS NOT NULL) OR (status IN ('APPROVED', 'REJECTED') AND current_step_order IS NULL))");
        DB::statement('ALTER TABLE leave_request_revisions ADD CONSTRAINT lrrev_units_valid CHECK (requested_units > 0)');
        DB::statement("ALTER TABLE leave_request_days ADD CONSTRAINT lrd_session_valid CHECK (session_code IN ('AM', 'PM'))");
        DB::statement("ALTER TABLE leave_approval_steps ADD CONSTRAINT las_stage_valid CHECK (stage_code IN ('UNIT_MANAGER_SUPPORT', 'GENERAL_MANAGER_SUPPORT', 'GENERAL_MANAGER_FINAL', 'SECRETARY_FINAL'))");
        DB::statement("ALTER TABLE leave_workflow_actions ADD CONSTRAINT lwa_action_valid CHECK (action_code IN ('SUPPORT', 'NOT_SUPPORT', 'APPROVE', 'REJECT'))");
        DB::statement("ALTER TABLE leave_attendance_reviews ADD CONSTRAINT lar_code_valid CHECK (review_code IN ('LATE_AL_REJECTION', 'RETROSPECTIVE_EL_REJECTION'))");

        foreach (['leave_request_revisions', 'leave_request_days', 'leave_approval_steps', 'leave_workflow_actions', 'leave_attendance_reviews'] as $table) {
            $this->createAppendOnlyTriggers($table);
        }
        DB::unprepared("CREATE TRIGGER leave_requests_no_delete BEFORE DELETE ON leave_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave requests cannot be deleted'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_requests_linked_employee_before_insert BEFORE INSERT ON leave_requests FOR EACH ROW BEGIN
DECLARE linked_user_id BIGINT UNSIGNED DEFAULT NULL;
SELECT user_id INTO linked_user_id FROM employees WHERE id = NEW.employee_id FOR UPDATE;
IF linked_user_id IS NULL OR linked_user_id <> NEW.requester_user_id THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A leave request must be created by the employee’s linked account';
END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_revisions_reservation_before_insert BEFORE INSERT ON leave_request_revisions FOR EACH ROW BEGIN
DECLARE request_employee_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE request_key_value VARCHAR(120) DEFAULT NULL;
DECLARE reservation_employee_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE reservation_request_key VARCHAR(120) DEFAULT NULL;
DECLARE reservation_revision INT UNSIGNED DEFAULT NULL;
SELECT employee_id, request_key INTO request_employee_id, request_key_value FROM leave_requests WHERE id = NEW.request_id;
SELECT employee_id, request_key, revision INTO reservation_employee_id, reservation_request_key, reservation_revision FROM leave_reservations WHERE id = NEW.reservation_id;
IF request_employee_id IS NULL OR reservation_employee_id <> request_employee_id OR reservation_request_key <> request_key_value OR reservation_revision <> NEW.revision THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A request revision must reference its matching employee reservation';
END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_workflow_actions_assignment_before_insert BEFORE INSERT ON leave_workflow_actions FOR EACH ROW BEGIN
DECLARE step_assignment_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE step_assignee_user_id BIGINT UNSIGNED DEFAULT NULL;
SELECT assignment_id, assignee_user_id INTO step_assignment_id, step_assignee_user_id FROM leave_approval_steps WHERE id = NEW.approval_step_id AND revision_id = NEW.revision_id;
IF step_assignment_id IS NULL OR step_assignment_id <> NEW.assignment_id OR step_assignee_user_id <> NEW.actor_id THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A workflow action must be made by the persisted step assignee';
END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_requests_state_transition BEFORE UPDATE ON leave_requests FOR EACH ROW BEGIN
DECLARE transition_valid BOOLEAN DEFAULT FALSE;
IF NOT (NEW.request_key <=> OLD.request_key) OR NOT (NEW.submission_key <=> OLD.submission_key) OR NOT (NEW.payload_hash <=> OLD.payload_hash) OR NOT (NEW.employee_id <=> OLD.employee_id) OR NOT (NEW.requester_user_id <=> OLD.requester_user_id) OR NOT (NEW.leave_type <=> OLD.leave_type) OR NOT (NEW.created_at <=> OLD.created_at) OR NEW.current_revision <> OLD.current_revision OR (OLD.attendance_review_required = 1 AND NEW.attendance_review_required = 0) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave request identity and submitted revision are immutable';
END IF;
IF OLD.status = 'WAITING_UNIT_MANAGER_SUPPORT' AND NEW.status IN ('WAITING_GENERAL_MANAGER_SUPPORT', 'WAITING_GENERAL_MANAGER_FINAL') AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status = 'WAITING_GENERAL_MANAGER_SUPPORT' AND NEW.status = 'WAITING_SECRETARY_FINAL' AND NEW.current_step_order = OLD.current_step_order + 1 AND NEW.attendance_review_required = OLD.attendance_review_required THEN
SET transition_valid = TRUE;
ELSEIF OLD.status IN ('WAITING_GENERAL_MANAGER_FINAL', 'WAITING_SECRETARY_FINAL') AND NEW.status IN ('APPROVED', 'REJECTED') AND NEW.current_step_order IS NULL AND (NEW.attendance_review_required = OLD.attendance_review_required OR (OLD.attendance_review_required = 0 AND NEW.status = 'REJECTED' AND NEW.attendance_review_required = 1)) THEN
SET transition_valid = TRUE;
END IF;
IF transition_valid = FALSE THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave request workflow transition is invalid';
END IF;
END
SQL);
    }

    /** @param 'leave_request_revisions'|'leave_request_days'|'leave_approval_steps'|'leave_workflow_actions'|'leave_attendance_reviews' $table */
    private function createAppendOnlyTriggers(string $table): void
    {
        DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave workflow history is immutable'");
        DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave workflow history cannot be deleted'");
    }

    public function down(): void
    {
        foreach (['leave_requests_state_transition', 'leave_requests_linked_employee_before_insert', 'leave_request_revisions_reservation_before_insert', 'leave_workflow_actions_assignment_before_insert', 'leave_requests_no_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        foreach (['leave_request_revisions', 'leave_request_days', 'leave_approval_steps', 'leave_workflow_actions', 'leave_attendance_reviews'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
        }
        Schema::dropIfExists('leave_attendance_reviews');
        Schema::dropIfExists('leave_workflow_actions');
        Schema::dropIfExists('leave_approval_steps');
        Schema::dropIfExists('leave_request_days');
        Schema::dropIfExists('leave_request_revisions');
        Schema::dropIfExists('leave_requests');
    }
};
