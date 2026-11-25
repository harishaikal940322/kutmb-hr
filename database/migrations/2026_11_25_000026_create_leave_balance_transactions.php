<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending()) {
            $this->pretendPartialContinuation();

            return;
        }

        if (! Schema::hasTable('leave_balance_operations')) {
            Schema::create('leave_balance_operations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->string('operation_key', 96)->unique();
                $table->string('operation_type', 24);
                $table->char('payload_hash', 64);
                $table->timestamp('created_at')->useCurrent();
            });
        }
        $this->assertColumns('leave_balance_operations', ['id', 'employee_id', 'actor_id', 'operation_key', 'operation_type', 'payload_hash', 'created_at']);
        $this->ensureForeignKey('leave_balance_operations', 'employee_id', 'employees');
        $this->ensureForeignKey('leave_balance_operations', 'actor_id', 'users');
        $this->ensureIndex('leave_balance_operations', ['operation_key'], 'lbo_operation_key_uq', true);

        if (! Schema::hasColumn('leave_ledger_entries', 'balance_operation_id')) {
            if (DB::table('leave_ledger_entries')->exists()) {
                throw new RuntimeException('Cannot attach balance operations to existing ledger entries without an approved backfill.');
            }
            Schema::table('leave_ledger_entries', function (Blueprint $table): void {
                $table->foreignId('balance_operation_id')->nullable()->constrained('leave_balance_operations')->restrictOnDelete();
            });
            Schema::table('leave_ledger_entries', function (Blueprint $table): void {
                $table->foreignId('balance_operation_id')->nullable(false)->change();
            });
        }
        $nullable = DB::selectOne("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'leave_ledger_entries' AND column_name = 'balance_operation_id'");
        if ($nullable === null || $nullable->IS_NULLABLE !== 'NO') {
            throw new RuntimeException('The balance operation ledger reference must be non-null before this migration can continue.');
        }
        $this->ensureForeignKey('leave_ledger_entries', 'balance_operation_id', 'leave_balance_operations');

        if (! Schema::hasTable('leave_reservations')) {
            Schema::create('leave_reservations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('history_marker_id')->constrained('leave_employee_history_markers')->restrictOnDelete();
                $table->string('request_key', 120);
                $table->unsignedInteger('revision');
                $table->unsignedSmallInteger('leave_year');
                $table->foreignId('annual_account_id')->constrained('leave_annual_accounts')->restrictOnDelete();
                $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts')->restrictOnDelete();
                $table->unsignedBigInteger('requested_units');
                $table->unsignedBigInteger('annual_units');
                $table->unsignedBigInteger('tabung_units');
                $table->unsignedSmallInteger('tabung_source_year')->nullable();
                $table->string('status', 16)->default('ACTIVE');
                $table->string('active_request_key', 120)->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('resolved_at')->nullable();
            });
        }
        if (! Schema::hasColumn('leave_reservations', 'history_marker_id')) {
            if (DB::table('leave_reservations')->exists()) {
                throw new RuntimeException('Cannot attach history markers to existing reservations without an approved backfill.');
            }
            Schema::table('leave_reservations', function (Blueprint $table): void {
                $table->foreignId('history_marker_id')->constrained('leave_employee_history_markers')->restrictOnDelete();
            });
        }
        $this->assertColumns('leave_reservations', ['id', 'employee_id', 'history_marker_id', 'request_key', 'revision', 'leave_year', 'annual_account_id', 'tabung_account_id', 'requested_units', 'annual_units', 'tabung_units', 'tabung_source_year', 'status', 'active_request_key', 'created_by', 'created_at', 'resolved_at']);
        $this->ensureForeignKey('leave_reservations', 'employee_id', 'employees');
        $this->ensureForeignKey('leave_reservations', 'history_marker_id', 'leave_employee_history_markers');
        $this->ensureForeignKey('leave_reservations', 'annual_account_id', 'leave_annual_accounts');
        $this->ensureForeignKey('leave_reservations', 'tabung_account_id', 'leave_tabung_accounts');
        $this->ensureForeignKey('leave_reservations', 'created_by', 'users');
        $this->ensureIndex('leave_reservations', ['request_key', 'revision'], 'lr_request_revision_uq', true);
        $this->ensureIndex('leave_reservations', ['active_request_key'], 'lr_active_request_uq', true);
        $this->ensureIndex('leave_reservations', ['employee_id', 'status'], 'lr_employee_status_idx');
        $this->ensureIndex('leave_reservations', ['annual_account_id', 'status'], 'lr_annual_status_idx');
        $this->ensureIndex('leave_reservations', ['tabung_account_id', 'tabung_source_year', 'status'], 'lr_tabung_source_status_idx');

        if (! Schema::hasTable('leave_reservation_events')) {
            Schema::create('leave_reservation_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reservation_id')->constrained('leave_reservations')->restrictOnDelete();
                $table->foreignId('balance_operation_id')->unique()->constrained('leave_balance_operations')->restrictOnDelete();
                $table->string('event_code', 16);
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        $this->assertColumns('leave_reservation_events', ['id', 'reservation_id', 'balance_operation_id', 'event_code', 'actor_id', 'created_at']);
        $this->ensureForeignKey('leave_reservation_events', 'reservation_id', 'leave_reservations');
        $this->ensureForeignKey('leave_reservation_events', 'balance_operation_id', 'leave_balance_operations');
        $this->ensureForeignKey('leave_reservation_events', 'actor_id', 'users');
        $this->ensureIndex('leave_reservation_events', ['balance_operation_id'], 'lre_balance_operation_uq', true);

        if (DB::getDriverName() === 'mysql') {
            $this->ensureCheck('leave_balance_operations', 'lbo_operation_key_nonempty', "operation_key <> ''");
            $this->ensureCheck('leave_reservations', 'lr_allocation_valid', 'requested_units > 0 AND annual_units <= requested_units AND tabung_units = requested_units - annual_units AND ((tabung_units = 0 AND tabung_source_year IS NULL) OR (tabung_units > 0 AND tabung_source_year IS NOT NULL))');
            $this->ensureCheck('leave_reservations', 'lr_state_valid', "(status = 'ACTIVE' AND active_request_key = request_key AND resolved_at IS NULL) OR (status IN ('COMMITTED', 'RELEASED') AND active_request_key IS NULL AND resolved_at IS NOT NULL)");

            $this->ensureTrigger('leave_balance_operations_no_update', "CREATE TRIGGER leave_balance_operations_no_update BEFORE UPDATE ON leave_balance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave balance operations are immutable'");
            $this->ensureTrigger('leave_balance_operations_no_delete', "CREATE TRIGGER leave_balance_operations_no_delete BEFORE DELETE ON leave_balance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave balance operations are immutable'");
            $this->ensureTrigger('leave_reservation_events_no_update', "CREATE TRIGGER leave_reservation_events_no_update BEFORE UPDATE ON leave_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservation events are append-only'");
            $this->ensureTrigger('leave_reservation_events_no_delete', "CREATE TRIGGER leave_reservation_events_no_delete BEFORE DELETE ON leave_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservation events are append-only'");
            $this->ensureTrigger('leave_reservations_no_delete', "CREATE TRIGGER leave_reservations_no_delete BEFORE DELETE ON leave_reservations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservations cannot be deleted'");
            $this->ensureTrigger('leave_reservations_state_transition', "CREATE TRIGGER leave_reservations_state_transition BEFORE UPDATE ON leave_reservations FOR EACH ROW BEGIN IF OLD.status <> 'ACTIVE' OR NEW.status NOT IN ('COMMITTED', 'RELEASED') OR NEW.active_request_key IS NOT NULL OR NEW.resolved_at IS NULL OR NOT (OLD.id <=> NEW.id) OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.history_marker_id <=> NEW.history_marker_id) OR NOT (OLD.request_key <=> NEW.request_key) OR NOT (OLD.revision <=> NEW.revision) OR NOT (OLD.leave_year <=> NEW.leave_year) OR NOT (OLD.annual_account_id <=> NEW.annual_account_id) OR NOT (OLD.tabung_account_id <=> NEW.tabung_account_id) OR NOT (OLD.requested_units <=> NEW.requested_units) OR NOT (OLD.annual_units <=> NEW.annual_units) OR NOT (OLD.tabung_units <=> NEW.tabung_units) OR NOT (OLD.tabung_source_year <=> NEW.tabung_source_year) OR NOT (OLD.created_by <=> NEW.created_by) OR NOT (OLD.created_at <=> NEW.created_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one terminal reservation transition is allowed'; END IF; END");
        }
    }

    /** @param list<string> $columns */
    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                throw new RuntimeException("The partial {$table} table is missing required column {$column}; migration stopped without altering existing data.");
            }
        }
    }

    private function pretendPartialContinuation(): void
    {
        DB::statement('ALTER TABLE leave_reservations ADD history_marker_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE leave_reservations ADD CONSTRAINT leave_reservations_history_marker_id_foreign FOREIGN KEY (history_marker_id) REFERENCES leave_employee_history_markers (id) ON DELETE RESTRICT');
        DB::statement('CREATE UNIQUE INDEX lr_active_request_uq ON leave_reservations (active_request_key)');
        DB::statement('CREATE INDEX lr_tabung_source_status_idx ON leave_reservations (tabung_account_id, tabung_source_year, status)');
        Schema::create('leave_reservation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained('leave_reservations')->restrictOnDelete();
            $table->foreignId('balance_operation_id')->unique()->constrained('leave_balance_operations')->restrictOnDelete();
            $table->string('event_code', 16);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE leave_balance_operations ADD CONSTRAINT lbo_operation_key_nonempty CHECK (operation_key <> '')");
        DB::statement('ALTER TABLE leave_reservations ADD CONSTRAINT lr_allocation_valid CHECK (requested_units > 0 AND annual_units <= requested_units AND tabung_units = requested_units - annual_units AND ((tabung_units = 0 AND tabung_source_year IS NULL) OR (tabung_units > 0 AND tabung_source_year IS NOT NULL)))');
        DB::statement("ALTER TABLE leave_reservations ADD CONSTRAINT lr_state_valid CHECK ((status = 'ACTIVE' AND active_request_key = request_key AND resolved_at IS NULL) OR (status IN ('COMMITTED', 'RELEASED') AND active_request_key IS NULL AND resolved_at IS NOT NULL))");
        DB::unprepared("CREATE TRIGGER leave_balance_operations_no_update BEFORE UPDATE ON leave_balance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave balance operations are immutable'");
        DB::unprepared("CREATE TRIGGER leave_balance_operations_no_delete BEFORE DELETE ON leave_balance_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave balance operations are immutable'");
        DB::unprepared("CREATE TRIGGER leave_reservation_events_no_update BEFORE UPDATE ON leave_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservation events are append-only'");
        DB::unprepared("CREATE TRIGGER leave_reservation_events_no_delete BEFORE DELETE ON leave_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservation events are append-only'");
        DB::unprepared("CREATE TRIGGER leave_reservations_no_delete BEFORE DELETE ON leave_reservations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave reservations cannot be deleted'");
        DB::unprepared("CREATE TRIGGER leave_reservations_state_transition BEFORE UPDATE ON leave_reservations FOR EACH ROW BEGIN IF OLD.status <> 'ACTIVE' OR NEW.status NOT IN ('COMMITTED', 'RELEASED') OR NEW.active_request_key IS NOT NULL OR NEW.resolved_at IS NULL OR NOT (OLD.history_marker_id <=> NEW.history_marker_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one terminal reservation transition is allowed'; END IF; END");
    }

    private function ensureForeignKey(string $table, string $column, string $referencedTable): void
    {
        $exists = DB::selectOne('SELECT 1 AS found FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name = ? AND referenced_column_name = \'id\' LIMIT 1', [$table, $column, $referencedTable]);
        if ($exists === null) {
            throw new RuntimeException("The {$table}.{$column} foreign key to {$referencedTable} is missing; migration stopped without repairing an unknown partial constraint.");
        }
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        $indexes = DB::select('SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR \',\') AS columns_in_order, MIN(non_unique) AS non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? GROUP BY index_name', [$table]);
        foreach ($indexes as $index) {
            if ($index->columns_in_order === implode(',', $columns) && ((int) $index->non_unique === 0) === $unique) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name, $unique): void {
            $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
        });
    }

    private function ensureCheck(string $table, string $name, string $expression): void
    {
        $exists = DB::selectOne('SELECT 1 AS found FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = \'CHECK\' LIMIT 1', [$table, $name]);
        if ($exists === null) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    /** @param literal-string $sql */
    private function ensureTrigger(string $name, string $sql): void
    {
        $exists = DB::selectOne('SELECT 1 AS found FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = ? LIMIT 1', [$name]);
        if ($exists === null) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS leave_reservations_state_transition');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_reservations_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_events_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_events_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_balance_operations_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_balance_operations_no_update');
        }
        Schema::dropIfExists('leave_reservation_events');
        Schema::dropIfExists('leave_reservations');
        if (Schema::hasColumn('leave_ledger_entries', 'balance_operation_id')) {
            Schema::table('leave_ledger_entries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('balance_operation_id');
            });
        }
        Schema::dropIfExists('leave_balance_operations');
    }
};
