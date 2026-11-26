<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Per-date reservation source guards require the selected MySQL database.');
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
OR reservation_tabung_source_year IS NULL OR NEW.source_year < reservation_tabung_source_year THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung slot allocation must use the reservation employee account and source year';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported leave slot allocation bucket';
END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slots_terminal_guard');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_reservation_slots_terminal_guard BEFORE UPDATE ON leave_reservations FOR EACH ROW BEGIN
DECLARE allocation_count BIGINT UNSIGNED DEFAULT 0;
DECLARE annual_units BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_units BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_source_count BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_source_year SMALLINT UNSIGNED DEFAULT NULL;
DECLARE active_slot_count BIGINT UNSIGNED DEFAULT 0;
DECLARE unmatched_allocation_count BIGINT UNSIGNED DEFAULT 0;
DECLARE unmatched_active_slot_count BIGINT UNSIGNED DEFAULT 0;
IF NEW.status <> OLD.status AND NEW.status IN ('COMMITTED', 'RELEASED', 'CANCELLED')
AND EXISTS (SELECT 1 FROM leave_reservation_slot_allocations WHERE reservation_id = OLD.id) THEN
SELECT COUNT(*),
COALESCE(SUM(CASE WHEN allocation_bucket = 'ANNUAL' THEN units ELSE 0 END), 0),
COALESCE(SUM(CASE WHEN allocation_bucket = 'TABUNG' THEN units ELSE 0 END), 0),
COUNT(DISTINCT CASE WHEN allocation_bucket = 'TABUNG' THEN source_year END),
MIN(CASE WHEN allocation_bucket = 'TABUNG' THEN source_year END)
INTO allocation_count, annual_units, tabung_units, tabung_source_count, tabung_source_year
FROM leave_reservation_slot_allocations WHERE reservation_id = OLD.id;
SELECT COUNT(*) INTO active_slot_count FROM leave_active_slots WHERE reservation_id = OLD.id;
SELECT COUNT(*) INTO unmatched_allocation_count
FROM leave_reservation_slot_allocations AS allocation
LEFT JOIN leave_active_slots AS active_slot
ON active_slot.reservation_id = allocation.reservation_id
AND active_slot.leave_date = allocation.leave_date
AND active_slot.session_code = allocation.session_code
WHERE allocation.reservation_id = OLD.id AND active_slot.id IS NULL;
SELECT COUNT(*) INTO unmatched_active_slot_count
FROM leave_active_slots AS active_slot
LEFT JOIN leave_reservation_slot_allocations AS allocation
ON allocation.reservation_id = active_slot.reservation_id
AND allocation.leave_date = active_slot.leave_date
AND allocation.session_code = active_slot.session_code
WHERE active_slot.reservation_id = OLD.id AND allocation.id IS NULL;
IF allocation_count <> NEW.requested_units OR active_slot_count <> NEW.requested_units OR allocation_count = 0
OR annual_units <> NEW.annual_units OR tabung_units <> NEW.tabung_units
OR unmatched_allocation_count <> 0 OR unmatched_active_slot_count <> 0
OR (tabung_units = 0 AND NEW.tabung_source_year IS NOT NULL)
OR (tabung_units > 0 AND (tabung_source_count < 1 OR NOT (tabung_source_year <=> NEW.tabung_source_year))) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation transition requires an exact per-date source and active-slot map';
END IF;
END IF;
END
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('FIFO source allocation is forward-only; reverting would invalidate existing multi-cohort reservations.');
    }
};
