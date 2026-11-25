<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER leave_ledger_validate_reversal BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN DECLARE original_annual_account_id BIGINT UNSIGNED DEFAULT NULL; DECLARE original_tabung_account_id BIGINT UNSIGNED DEFAULT NULL; DECLARE original_units BIGINT DEFAULT NULL; IF NEW.reverses_entry_id IS NOT NULL THEN SELECT annual_account_id, tabung_account_id, signed_half_day_units INTO original_annual_account_id, original_tabung_account_id, original_units FROM leave_ledger_entries WHERE id = NEW.reverses_entry_id; IF original_units IS NULL OR NOT (NEW.annual_account_id <=> original_annual_account_id) OR NOT (NEW.tabung_account_id <=> original_tabung_account_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave ledger reversals must use the original account'; END IF; IF NEW.signed_half_day_units = 0 OR original_units = 0 OR (NEW.signed_half_day_units > 0) = (original_units > 0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave ledger reversals must have the opposite nonzero sign'; END IF; END IF; END");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_validate_reversal');
    }
};
