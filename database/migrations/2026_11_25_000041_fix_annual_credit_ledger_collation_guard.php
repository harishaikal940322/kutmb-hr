<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('The annual-credit ledger guard is verified only for the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_annual_credit_ledger_guard');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_annual_credit_ledger_guard BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN
DECLARE operation_employee_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE operation_actor_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE operation_key_value VARCHAR(96) DEFAULT NULL;
DECLARE operation_type_value VARCHAR(24) DEFAULT NULL;
DECLARE account_employee_id BIGINT UNSIGNED DEFAULT NULL;
DECLARE account_year SMALLINT UNSIGNED DEFAULT NULL;
DECLARE confirmed_units BIGINT UNSIGNED DEFAULT NULL;
DECLARE confirmed_year SMALLINT UNSIGNED DEFAULT NULL;
IF BINARY NEW.kind = BINARY 'annual_entitlement_credit' THEN
SELECT employee_id, actor_id, operation_key, operation_type INTO operation_employee_id, operation_actor_id, operation_key_value, operation_type_value
FROM leave_balance_operations WHERE id = NEW.balance_operation_id;
SELECT employee_id, leave_year INTO account_employee_id, account_year FROM leave_annual_accounts WHERE id = NEW.annual_account_id;
SELECT confirmed_half_day_units, leave_year INTO confirmed_units, confirmed_year
FROM leave_annual_entitlement_confirmations WHERE annual_account_id = NEW.annual_account_id;
IF NEW.annual_account_id IS NULL OR NEW.tabung_account_id IS NOT NULL OR NEW.signed_half_day_units <= 0
OR NEW.source_year IS NOT NULL OR NEW.reverses_entry_id IS NOT NULL
OR operation_employee_id IS NULL OR BINARY operation_type_value <> BINARY 'ANNUAL_CREDIT' OR operation_employee_id <> account_employee_id
OR operation_actor_id <> NEW.actor_id OR BINARY operation_key_value <> BINARY NEW.operation_key
OR BINARY NEW.operation_key <> BINARY CONCAT('annual-credit:', NEW.annual_account_id, ':', account_year)
OR confirmed_units IS NULL OR confirmed_year <> account_year OR confirmed_units <> NEW.signed_half_day_units THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An annual entitlement credit must match its confirmed account, year, actor, amount and unique operation';
END IF;
END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('The corrected annual-credit ledger guard is retained as integrity history.');
    }
};
