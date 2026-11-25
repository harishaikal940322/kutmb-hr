<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Partial leave cancellation integrity is verified only for the selected MySQL database.');
        }

        if (! Schema::hasTable('leave_request_partial_cancellations')) {
            Schema::create('leave_request_partial_cancellations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('request_id')->constrained('leave_requests', 'id', 'lrpc_request_fk')->restrictOnDelete();
                $table->unsignedBigInteger('revision_id');
                $table->foreign(['revision_id', 'request_id'], 'lrpc_revision_request_fk')
                    ->references(['id', 'request_id'])->on('leave_request_revisions')->restrictOnDelete();
                $table->unsignedInteger('cycle');
                $table->string('status', 16)->default('REQUESTED');
                $table->foreignId('requested_by')->constrained('users', 'id', 'lrpc_requester_fk')->restrictOnDelete();
                $table->text('request_reason');
                $table->string('request_operation_key', 80)->unique('lrpc_request_op_uq');
                $table->char('request_payload_hash', 64);
                $table->date('actual_return_date')->nullable();
                $table->unsignedSmallInteger('unused_half_day_units')->nullable();
                $table->foreignId('hr_verified_by')->nullable()->constrained('users', 'id', 'lrpc_hr_reviewer_fk')->restrictOnDelete();
                $table->text('hr_reason')->nullable();
                $table->string('hr_operation_key', 80)->nullable()->unique('lrpc_hr_op_uq');
                $table->char('hr_payload_hash', 64)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('general_manager_user_id')->nullable()->constrained('users', 'id', 'lrpc_gm_user_fk')->restrictOnDelete();
                $table->foreignId('general_manager_assignment_id')->nullable()->constrained('approval_assignments', 'id', 'lrpc_gm_assignment_fk')->restrictOnDelete();
                $table->text('decision_reason')->nullable();
                $table->string('decision_operation_key', 80)->nullable()->unique('lrpc_decision_op_uq');
                $table->char('decision_payload_hash', 64)->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->unsignedBigInteger('active_request_id')->nullable()
                    ->storedAs("CASE WHEN status IN ('REQUESTED', 'HR_VERIFIED') THEN request_id ELSE NULL END");
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['request_id', 'cycle'], 'lrpc_request_cycle_uq');
                $table->unique('active_request_id', 'lrpc_active_request_uq');
                $table->index(['request_id', 'revision_id', 'status'], 'lrpc_request_revision_status_idx');
            });
        }

        $this->ensureForeignKey('leave_request_partial_cancellations', 'request_id', 'leave_requests', 'id', 'lrpc_request_fk');
        $this->ensureForeignKey('leave_request_partial_cancellations', 'requested_by', 'users', 'id', 'lrpc_requester_fk');
        $this->ensureForeignKey('leave_request_partial_cancellations', 'hr_verified_by', 'users', 'id', 'lrpc_hr_reviewer_fk');
        $this->ensureForeignKey('leave_request_partial_cancellations', 'general_manager_user_id', 'users', 'id', 'lrpc_gm_user_fk');
        $this->ensureForeignKey('leave_request_partial_cancellations', 'general_manager_assignment_id', 'approval_assignments', 'id', 'lrpc_gm_assignment_fk');
        $this->ensureCompositeRevisionForeignKey();
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_request_cycle_uq', ['request_id', 'cycle'], true);
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_active_request_uq', ['active_request_id'], true);
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_request_revision_status_idx', ['request_id', 'revision_id', 'status']);
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_request_op_uq', ['request_operation_key'], true);
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_hr_op_uq', ['hr_operation_key'], true);
        $this->ensureIndex('leave_request_partial_cancellations', 'lrpc_decision_op_uq', ['decision_operation_key'], true);

        if (! Schema::hasTable('leave_request_partial_cancellation_events')) {
            Schema::create('leave_request_partial_cancellation_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('partial_cancellation_id')->constrained('leave_request_partial_cancellations', 'id', 'lrpce_case_fk')->restrictOnDelete();
                $table->string('event_code', 16);
                $table->foreignId('actor_id')->constrained('users', 'id', 'lrpce_actor_fk')->restrictOnDelete();
                $table->foreignId('assignment_id')->nullable()->constrained('approval_assignments', 'id', 'lrpce_assignment_fk')->restrictOnDelete();
                $table->text('reason')->nullable();
                $table->date('actual_return_date')->nullable();
                $table->unsignedSmallInteger('unused_half_day_units')->nullable();
                $table->string('operation_key', 80)->unique('lrpce_operation_key_uq');
                $table->char('payload_hash', 64);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['partial_cancellation_id', 'event_code'], 'lrpce_case_event_uq');
                $table->index(['partial_cancellation_id', 'created_at'], 'lrpce_case_created_idx');
            });
        }

        $this->ensureForeignKey('leave_request_partial_cancellation_events', 'partial_cancellation_id', 'leave_request_partial_cancellations', 'id', 'lrpce_case_fk');
        $this->ensureForeignKey('leave_request_partial_cancellation_events', 'actor_id', 'users', 'id', 'lrpce_actor_fk');
        $this->ensureForeignKey('leave_request_partial_cancellation_events', 'assignment_id', 'approval_assignments', 'id', 'lrpce_assignment_fk');
        $this->ensureIndex('leave_request_partial_cancellation_events', 'lrpce_case_event_uq', ['partial_cancellation_id', 'event_code'], true);
        $this->ensureIndex('leave_request_partial_cancellation_events', 'lrpce_case_created_idx', ['partial_cancellation_id', 'created_at']);
        $this->ensureIndex('leave_request_partial_cancellation_events', 'lrpce_operation_key_uq', ['operation_key'], true);

        $this->ensureCheckConstraint('leave_request_partial_cancellations', 'lrpc_state_valid', "(status = 'REQUESTED' AND actual_return_date IS NULL AND unused_half_day_units IS NULL AND hr_verified_by IS NULL AND hr_reason IS NULL AND hr_operation_key IS NULL AND hr_payload_hash IS NULL AND verified_at IS NULL AND general_manager_user_id IS NULL AND general_manager_assignment_id IS NULL AND decision_reason IS NULL AND decision_operation_key IS NULL AND decision_payload_hash IS NULL AND decided_at IS NULL) OR (status = 'HR_VERIFIED' AND actual_return_date IS NOT NULL AND unused_half_day_units > 0 AND hr_verified_by IS NOT NULL AND hr_reason IS NOT NULL AND TRIM(hr_reason) <> '' AND hr_operation_key IS NOT NULL AND hr_payload_hash IS NOT NULL AND verified_at IS NOT NULL AND general_manager_user_id IS NULL AND general_manager_assignment_id IS NULL AND decision_operation_key IS NULL AND decision_payload_hash IS NULL AND decided_at IS NULL) OR (status IN ('CONFIRMED', 'REJECTED') AND actual_return_date IS NOT NULL AND unused_half_day_units > 0 AND hr_verified_by IS NOT NULL AND general_manager_user_id IS NOT NULL AND general_manager_assignment_id IS NOT NULL AND decision_operation_key IS NOT NULL AND decision_payload_hash IS NOT NULL AND decided_at IS NOT NULL)");
        $this->ensureCheckConstraint('leave_request_partial_cancellation_events', 'lrpce_event_valid', "event_code IN ('REQUESTED', 'HR_VERIFIED', 'CONFIRMED', 'REJECTED')");

        foreach ([
            'leave_request_partial_cancellations_before_insert',
            'leave_request_partial_cancellations_state_transition',
            'leave_request_partial_cancellations_no_delete',
            'leave_request_partial_cancellation_events_before_insert',
            'leave_request_partial_cancellation_events_no_update',
            'leave_request_partial_cancellation_events_no_delete',
        ] as $trigger) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        }

        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_before_insert BEFORE INSERT ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
IF NEW.status <> 'REQUESTED' OR NEW.actual_return_date IS NOT NULL OR NEW.unused_half_day_units IS NOT NULL OR NEW.hr_verified_by IS NOT NULL OR NEW.general_manager_user_id IS NOT NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A partial leave cancellation must begin with an employee request';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN employees AS e ON e.id = q.employee_id INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND e.user_id = NEW.requested_by AND q.requester_user_id = NEW.requested_by AND b.status = 'COMMITTED' AND b.tabung_units = 0 AND b.annual_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation requires the linked employee and a committed annual-funded approved leave'; END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellations_state_transition BEFORE UPDATE ON leave_request_partial_cancellations FOR EACH ROW BEGIN
DECLARE matching_request_count INT DEFAULT 0;
DECLARE matching_unused_slots INT DEFAULT 0;
DECLARE assignment_count INT DEFAULT 0;
IF NOT (OLD.id <=> NEW.id) OR NOT (OLD.request_id <=> NEW.request_id) OR NOT (OLD.revision_id <=> NEW.revision_id) OR NOT (OLD.cycle <=> NEW.cycle) OR NOT (OLD.requested_by <=> NEW.requested_by) OR NOT (OLD.request_reason <=> NEW.request_reason) OR NOT (OLD.request_operation_key <=> NEW.request_operation_key) OR NOT (OLD.request_payload_hash <=> NEW.request_payload_hash) OR NOT (OLD.created_at <=> NEW.created_at) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation identity and employee request are immutable';
END IF;
SELECT COUNT(*) INTO matching_request_count FROM leave_requests AS q INNER JOIN leave_request_revisions AS r ON r.request_id = q.id AND r.id = NEW.revision_id AND r.revision = q.current_revision INNER JOIN leave_reservations AS b ON b.id = r.reservation_id WHERE q.id = NEW.request_id AND q.status = 'APPROVED' AND q.leave_type = 'AL' AND b.status = 'COMMITTED' AND b.tabung_units = 0 AND b.annual_units > 0;
IF matching_request_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The approved annual leave and committed reservation must remain current during partial cancellation';
ELSEIF OLD.status = 'REQUESTED' AND NEW.status = 'HR_VERIFIED' AND OLD.actual_return_date IS NULL AND NEW.actual_return_date IS NOT NULL AND NEW.unused_half_day_units > 0 AND NEW.hr_verified_by IS NOT NULL AND NEW.hr_reason IS NOT NULL AND TRIM(NEW.hr_reason) <> '' AND NEW.hr_operation_key IS NOT NULL AND NEW.hr_payload_hash IS NOT NULL AND NEW.verified_at IS NOT NULL AND NEW.general_manager_user_id IS NULL AND NEW.general_manager_assignment_id IS NULL AND NEW.decision_operation_key IS NULL AND NEW.decision_payload_hash IS NULL AND NEW.decided_at IS NULL THEN
SELECT COUNT(*) INTO matching_unused_slots FROM leave_request_days WHERE revision_id = NEW.revision_id AND leave_date > NEW.actual_return_date;
IF matching_unused_slots <> NEW.unused_half_day_units OR EXISTS (SELECT 1 FROM leave_request_days WHERE revision_id = NEW.revision_id AND leave_date = NEW.actual_return_date) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified unused units must match whole requested sessions after the actual return date';
END IF;
ELSEIF OLD.status = 'HR_VERIFIED' AND NEW.status IN ('CONFIRMED', 'REJECTED') AND NEW.actual_return_date <=> OLD.actual_return_date AND NEW.unused_half_day_units <=> OLD.unused_half_day_units AND NEW.hr_verified_by <=> OLD.hr_verified_by AND NEW.hr_reason <=> OLD.hr_reason AND NEW.hr_operation_key <=> OLD.hr_operation_key AND NEW.hr_payload_hash <=> OLD.hr_payload_hash AND NEW.verified_at <=> OLD.verified_at AND NEW.general_manager_user_id IS NOT NULL AND NEW.general_manager_assignment_id IS NOT NULL AND NEW.decision_operation_key IS NOT NULL AND NEW.decision_payload_hash IS NOT NULL AND NEW.decided_at IS NOT NULL THEN
SELECT COUNT(*) INTO assignment_count FROM approval_assignments WHERE id = NEW.general_manager_assignment_id AND user_id = NEW.general_manager_user_id AND office_code = 'PB';
IF assignment_count <> 1 OR NEW.general_manager_user_id = NEW.requested_by THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation decisions require a distinct General Manager assignment';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation state transition is invalid';
END IF;
END
SQL);

        DB::unprepared("CREATE TRIGGER leave_request_partial_cancellations_no_delete BEFORE DELETE ON leave_request_partial_cancellations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation history cannot be deleted'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_request_partial_cancellation_events_before_insert BEFORE INSERT ON leave_request_partial_cancellation_events FOR EACH ROW BEGIN
DECLARE matching_count INT DEFAULT 0;
SELECT COUNT(*) INTO matching_count FROM leave_request_partial_cancellations WHERE id = NEW.partial_cancellation_id AND status = NEW.event_code AND ((NEW.event_code = 'REQUESTED' AND requested_by = NEW.actor_id AND request_reason <=> NEW.reason AND request_operation_key = NEW.operation_key AND request_payload_hash = NEW.payload_hash AND NEW.assignment_id IS NULL) OR (NEW.event_code = 'HR_VERIFIED' AND hr_verified_by = NEW.actor_id AND actual_return_date <=> NEW.actual_return_date AND unused_half_day_units <=> NEW.unused_half_day_units AND hr_reason <=> NEW.reason AND hr_operation_key = NEW.operation_key AND hr_payload_hash = NEW.payload_hash AND NEW.assignment_id IS NULL) OR (NEW.event_code IN ('CONFIRMED', 'REJECTED') AND general_manager_user_id = NEW.actor_id AND general_manager_assignment_id = NEW.assignment_id AND decision_reason <=> NEW.reason AND decision_operation_key = NEW.operation_key AND decision_payload_hash = NEW.payload_hash AND NEW.actual_return_date IS NULL AND NEW.unused_half_day_units IS NULL));
IF matching_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation event must match its recorded state'; END IF;
END
SQL
        );
        DB::unprepared("CREATE TRIGGER leave_request_partial_cancellation_events_no_update BEFORE UPDATE ON leave_request_partial_cancellation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation events are immutable'");
        DB::unprepared("CREATE TRIGGER leave_request_partial_cancellation_events_no_delete BEFORE DELETE ON leave_request_partial_cancellation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial cancellation events cannot be deleted'");

        DB::unprepared('DROP TRIGGER IF EXISTS leave_active_slots_state_transition');
        DB::unprepared($this->activeSlotTransitionTrigger());

        if (app()->environment(['local', 'testing'])) {
            DB::table('permissions')->insertOrIgnore([
                'code' => 'leave.partial-cancellation.review',
                'name' => 'leave.partial-cancellation.review',
                'guard_name' => 'web',
                'label' => 'Review partial leave cancellations',
                'description' => 'Verify synthetic local partial Annual Leave cancellation return details.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function activeSlotTransitionTrigger(): string
    {
        return <<<'SQL'
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
SELECT COUNT(*) INTO partial_cancellation_count FROM leave_request_partial_cancellations AS p INNER JOIN leave_request_revisions AS r ON r.id = p.revision_id AND r.request_id = p.request_id INNER JOIN leave_requests AS q ON q.id = p.request_id WHERE r.reservation_id = OLD.reservation_id AND r.revision = q.current_revision AND q.status = 'APPROVED' AND p.status = 'CONFIRMED' AND OLD.leave_date > p.actual_return_date;
IF partial_cancellation_count = 1 THEN SET transition_valid = TRUE; END IF;
END IF;
IF transition_valid = FALSE THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one valid leave slot transition is allowed'; END IF;
END
SQL;
    }

    private function ensureForeignKey(string $table, string $column, string $referencedTable, string $referencedColumn, string $constraint): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = ? LIMIT 1',
            [$table, $column, $referencedTable, $referencedColumn],
        );
        if ($exists === null) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`) ON DELETE RESTRICT");
        }
    }

    private function ensureCompositeRevisionForeignKey(): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND referenced_table_name = ? LIMIT 1',
            ['leave_request_partial_cancellations', 'lrpc_revision_request_fk', 'leave_request_revisions'],
        );
        if ($exists === null) {
            DB::statement('ALTER TABLE `leave_request_partial_cancellations` ADD CONSTRAINT `lrpc_revision_request_fk` FOREIGN KEY (`revision_id`, `request_id`) REFERENCES `leave_request_revisions` (`id`, `request_id`) ON DELETE RESTRICT');
        }
    }

    private function ensureCheckConstraint(string $table, string $constraint, string $expression): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$table, $constraint, 'CHECK'],
        );
        if ($exists === null) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` CHECK ({$expression})");
        }
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, string $index, array $columns, bool $unique = false): void
    {
        $exists = DB::selectOne(
            'SELECT 1 AS found FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        );
        if ($exists !== null) {
            return;
        }
        $columnList = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', $columns));
        $kind = $unique ? 'UNIQUE ' : '';
        DB::statement("ALTER TABLE `{$table}` ADD {$kind}INDEX `{$index}` ({$columnList})");
    }

    public function down(): void
    {
        throw new RuntimeException('Partial cancellation history and slot transitions are intentionally not reversible.');
    }
};
