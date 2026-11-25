<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pretending = DB::connection()->pretending();
        if (! $pretending && DB::table('leave_ledger_entries')->exists()) {
            throw new RuntimeException('Aggregate reversal protection requires an empty ledger or a separately reviewed reversal-total backfill.');
        }

        if (! Schema::hasTable('leave_ledger_reversal_totals')) {
            Schema::create('leave_ledger_reversal_totals', function (Blueprint $table): void {
                $table->unsignedBigInteger('original_entry_id')->primary();
                $table->unsignedBigInteger('reversed_units')->default(0);
                $table->foreign('original_entry_id', 'llrt_original_entry_fk')->references('id')->on('leave_ledger_entries')->restrictOnDelete();
            });
        }
        if (! $pretending) {
            if (! Schema::hasColumn('leave_ledger_reversal_totals', 'original_entry_id') || ! Schema::hasColumn('leave_ledger_reversal_totals', 'reversed_units')) {
                throw new RuntimeException('The partial leave ledger reversal totals table is missing required columns.');
            }

            $foreignKey = DB::selectOne("SELECT 1 AS found FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = 'leave_ledger_reversal_totals' AND column_name = 'original_entry_id' AND referenced_table_name = 'leave_ledger_entries' AND referenced_column_name = 'id' LIMIT 1");
            if ($foreignKey === null) {
                throw new RuntimeException('The aggregate reversal total must reference its original ledger entry.');
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $this->ensureTrigger('leave_ledger_reversal_totals_insert_guard', <<<'SQL'
CREATE TRIGGER leave_ledger_reversal_totals_insert_guard BEFORE INSERT ON leave_ledger_reversal_totals FOR EACH ROW BEGIN IF NEW.reversed_units <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reversal totals must start at zero'; END IF; END
SQL);
            $this->ensureTrigger('leave_ledger_reversal_totals_monotonic', <<<'SQL'
CREATE TRIGGER leave_ledger_reversal_totals_monotonic BEFORE UPDATE ON leave_ledger_reversal_totals FOR EACH ROW BEGIN DECLARE original_units BIGINT DEFAULT NULL; IF NOT (NEW.original_entry_id <=> OLD.original_entry_id) OR NEW.reversed_units < OLD.reversed_units THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reversal totals cannot be reassigned or decreased'; END IF; SELECT signed_half_day_units INTO original_units FROM leave_ledger_entries WHERE id = OLD.original_entry_id; IF original_units IS NULL OR CAST(NEW.reversed_units AS DECIMAL(20,0)) > ABS(CAST(original_units AS DECIMAL(20,0))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reversal totals cannot exceed the original entry'; END IF; END
SQL);
            $this->ensureTrigger('leave_ledger_reversal_totals_no_delete', "CREATE TRIGGER leave_ledger_reversal_totals_no_delete BEFORE DELETE ON leave_ledger_reversal_totals FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reversal totals cannot be deleted'");
            $this->ensureTrigger('leave_ledger_limit_aggregate_reversal', <<<'SQL'
CREATE TRIGGER leave_ledger_limit_aggregate_reversal BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN DECLARE original_units BIGINT DEFAULT NULL; DECLARE original_source_year SMALLINT UNSIGNED DEFAULT NULL; DECLARE original_limit DECIMAL(20,0) DEFAULT 0; DECLARE prior_reversed DECIMAL(20,0) DEFAULT 0; DECLARE reversal_units DECIMAL(20,0) DEFAULT 0; DECLARE resulting_reversed DECIMAL(20,0) DEFAULT 0; IF NEW.reverses_entry_id IS NOT NULL THEN SELECT signed_half_day_units, source_year INTO original_units, original_source_year FROM leave_ledger_entries WHERE id = NEW.reverses_entry_id; IF original_units IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The original ledger entry does not exist'; END IF; IF NOT (NEW.source_year <=> original_source_year) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A reversal must preserve the original source year'; END IF; SET original_limit = ABS(CAST(original_units AS DECIMAL(20,0))); SET reversal_units = ABS(CAST(NEW.signed_half_day_units AS DECIMAL(20,0))); INSERT IGNORE INTO leave_ledger_reversal_totals (original_entry_id, reversed_units) VALUES (NEW.reverses_entry_id, 0); SELECT reversed_units INTO prior_reversed FROM leave_ledger_reversal_totals WHERE original_entry_id = NEW.reverses_entry_id FOR UPDATE; SET resulting_reversed = prior_reversed + reversal_units; IF resulting_reversed > original_limit THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aggregate reversals cannot exceed the original ledger entry'; END IF; UPDATE leave_ledger_reversal_totals SET reversed_units = resulting_reversed WHERE original_entry_id = NEW.reverses_entry_id; END IF; END
SQL);
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
            DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_limit_aggregate_reversal');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_reversal_totals_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_reversal_totals_monotonic');
            DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_reversal_totals_insert_guard');
        }

        Schema::dropIfExists('leave_ledger_reversal_totals');
    }
};
