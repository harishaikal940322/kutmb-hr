<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The filename retains the already-applied local migration identity; the answer-aligned guard remains same-year.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Same-year reservation source validation requires the selected MySQL database.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slot_allocations_guard');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_reservation_slot_allocations_guard BEFORE INSERT ON leave_reservation_slot_allocations FOR EACH ROW BEGIN
DECLARE reservation_employee BIGINT UNSIGNED DEFAULT NULL;
DECLARE reservation_tabung BIGINT UNSIGNED DEFAULT NULL;
DECLARE reservation_tabung_source_year SMALLINT UNSIGNED DEFAULT NULL;
DECLARE account_employee BIGINT UNSIGNED DEFAULT NULL;
DECLARE account_year SMALLINT UNSIGNED DEFAULT NULL;
SELECT employee_id, tabung_account_id, tabung_source_year
INTO reservation_employee, reservation_tabung, reservation_tabung_source_year
FROM leave_reservations WHERE id = NEW.reservation_id;
IF reservation_employee IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot allocation requires an existing reservation'; END IF;
IF NEW.allocation_bucket = 'ANNUAL' THEN
SELECT employee_id, leave_year INTO account_employee, account_year FROM leave_annual_accounts WHERE id = NEW.annual_account_id;
IF account_employee IS NULL OR account_employee <> reservation_employee OR account_year <> NEW.leave_year THEN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Annual slot allocation must use the same employee and calendar year';
END IF;
IF NEW.tabung_account_id IS NOT NULL OR NEW.source_year IS NOT NULL THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Annual slot allocation cannot include a Tabung source';
END IF;
ELSEIF NEW.allocation_bucket = 'TABUNG' THEN
SELECT employee_id INTO account_employee FROM leave_tabung_accounts WHERE id = NEW.tabung_account_id;
IF account_employee IS NULL OR account_employee <> reservation_employee OR NEW.tabung_account_id <> reservation_tabung
OR NEW.source_year <> reservation_tabung_source_year THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung slot allocation must use the reservation employee account and source year';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported leave slot allocation bucket';
END IF;
END
SQL);
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_reservation_slot_allocations') && DB::table('leave_reservation_slot_allocations')->exists()) {
            throw new RuntimeException('Reservation source allocation history is not reversible.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slot_allocations_guard');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_reservation_slot_allocations_guard BEFORE INSERT ON leave_reservation_slot_allocations FOR EACH ROW BEGIN
DECLARE reservation_employee BIGINT UNSIGNED DEFAULT NULL;
DECLARE reservation_tabung BIGINT UNSIGNED DEFAULT NULL;
DECLARE account_employee BIGINT UNSIGNED DEFAULT NULL;
DECLARE account_year SMALLINT UNSIGNED DEFAULT NULL;
SELECT employee_id, tabung_account_id INTO reservation_employee, reservation_tabung FROM leave_reservations WHERE id = NEW.reservation_id;
IF reservation_employee IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot allocation requires an existing reservation'; END IF;
IF NEW.allocation_bucket = 'ANNUAL' THEN
SELECT employee_id, leave_year INTO account_employee, account_year FROM leave_annual_accounts WHERE id = NEW.annual_account_id;
IF account_employee IS NULL OR account_employee <> reservation_employee OR account_year <> NEW.leave_year THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Annual slot allocation must use the same employee and calendar year';
END IF;
ELSEIF NEW.allocation_bucket = 'TABUNG' THEN
SELECT employee_id INTO account_employee FROM leave_tabung_accounts WHERE id = NEW.tabung_account_id;
IF account_employee IS NULL OR account_employee <> reservation_employee OR NEW.tabung_account_id <> reservation_tabung THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung slot allocation must use the reservation employee account';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported leave slot allocation bucket';
END IF;
END
SQL);
    }
};
