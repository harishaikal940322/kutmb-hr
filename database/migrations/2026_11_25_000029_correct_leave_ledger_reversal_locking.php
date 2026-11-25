<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Aggregate reversal locking is verified only for the selected MySQL database.');
        }

        $exists = DB::selectOne('SELECT 1 AS found FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = ? LIMIT 1', ['leave_ledger_limit_aggregate_reversal_v2']);
        if ($exists === null) {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_ledger_limit_aggregate_reversal_v2 BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN DECLARE original_units BIGINT DEFAULT NULL; DECLARE original_source_year SMALLINT UNSIGNED DEFAULT NULL; DECLARE original_limit DECIMAL(20,0) DEFAULT 0; DECLARE prior_reversed DECIMAL(20,0) DEFAULT 0; DECLARE reversal_units DECIMAL(20,0) DEFAULT 0; DECLARE resulting_reversed DECIMAL(20,0) DEFAULT 0; IF NEW.reverses_entry_id IS NOT NULL THEN SELECT signed_half_day_units, source_year INTO original_units, original_source_year FROM leave_ledger_entries WHERE id = NEW.reverses_entry_id; IF original_units IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The original ledger entry does not exist'; END IF; IF NOT (NEW.source_year <=> original_source_year) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A reversal must preserve the original source year'; END IF; SET original_limit = ABS(CAST(original_units AS DECIMAL(20,0))); SET reversal_units = ABS(CAST(NEW.signed_half_day_units AS DECIMAL(20,0))); INSERT IGNORE INTO leave_ledger_reversal_totals (original_entry_id, reversed_units) VALUES (NEW.reverses_entry_id, 0); SELECT reversed_units INTO prior_reversed FROM leave_ledger_reversal_totals WHERE original_entry_id = NEW.reverses_entry_id FOR UPDATE; SET resulting_reversed = prior_reversed + reversal_units; IF resulting_reversed > original_limit THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aggregate reversals cannot exceed the original ledger entry'; END IF; UPDATE leave_ledger_reversal_totals SET reversed_units = resulting_reversed WHERE original_entry_id = NEW.reverses_entry_id; END IF; END
SQL);
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_limit_aggregate_reversal');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Aggregate reversal locking is verified only for the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_limit_aggregate_reversal_v2');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_ledger_limit_aggregate_reversal BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN DECLARE original_units BIGINT DEFAULT NULL; DECLARE original_source_year SMALLINT UNSIGNED DEFAULT NULL; DECLARE original_limit DECIMAL(20,0) DEFAULT 0; DECLARE prior_reversed DECIMAL(20,0) DEFAULT 0; DECLARE reversal_units DECIMAL(20,0) DEFAULT 0; DECLARE resulting_reversed DECIMAL(20,0) DEFAULT 0; IF NEW.reverses_entry_id IS NOT NULL THEN SELECT signed_half_day_units, source_year INTO original_units, original_source_year FROM leave_ledger_entries WHERE id = NEW.reverses_entry_id; IF original_units IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The original ledger entry does not exist'; END IF; IF NOT (NEW.source_year <=> original_source_year) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A reversal must preserve the original source year'; END IF; SET original_limit = ABS(CAST(original_units AS DECIMAL(20,0))); SET reversal_units = ABS(CAST(NEW.signed_half_day_units AS DECIMAL(20,0))); INSERT IGNORE INTO leave_ledger_reversal_totals (original_entry_id, reversed_units) VALUES (NEW.reverses_entry_id, 0); SELECT reversed_units INTO prior_reversed FROM leave_ledger_reversal_totals WHERE original_entry_id = NEW.reverses_entry_id FOR UPDATE; SET resulting_reversed = prior_reversed + reversal_units; IF resulting_reversed > original_limit THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Aggregate reversals cannot exceed the original ledger entry'; END IF; UPDATE leave_ledger_reversal_totals SET reversed_units = resulting_reversed WHERE original_entry_id = NEW.reverses_entry_id; END IF; END
SQL);
    }
};
