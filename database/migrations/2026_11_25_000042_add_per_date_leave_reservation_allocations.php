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
            throw new RuntimeException('Per-date leave reservation allocations require the selected MySQL database.');
        }

        if (! Schema::hasTable('leave_reservation_slot_allocations')) {
            Schema::create('leave_reservation_slot_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reservation_id')->constrained('leave_reservations')->restrictOnDelete();
                $table->date('leave_date');
                $table->unsignedSmallInteger('leave_year');
                $table->string('session_code', 2);
                $table->string('allocation_bucket', 8);
                $table->foreignId('annual_account_id')->nullable()->constrained('leave_annual_accounts')->restrictOnDelete();
                $table->foreignId('tabung_account_id')->nullable()->constrained('leave_tabung_accounts')->restrictOnDelete();
                $table->unsignedSmallInteger('source_year')->nullable();
                $table->unsignedTinyInteger('units')->default(1);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['reservation_id', 'leave_date', 'session_code'], 'lrsa_res_date_session_uq');
                $table->index(['annual_account_id', 'reservation_id'], 'lrsa_annual_reservation_idx');
                $table->index(['tabung_account_id', 'source_year', 'reservation_id'], 'lrsa_tabung_source_idx');
            });
        }

        if (! DB::table('information_schema.table_constraints')->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'leave_reservation_slot_allocations')->where('constraint_name', 'lrsa_source_valid')->exists()) {
            DB::statement("ALTER TABLE leave_reservation_slot_allocations ADD CONSTRAINT lrsa_source_valid CHECK (leave_year = YEAR(leave_date) AND session_code IN ('AM', 'PM') AND units = 1 AND ((allocation_bucket = 'ANNUAL' AND annual_account_id IS NOT NULL AND tabung_account_id IS NULL AND source_year IS NULL) OR (allocation_bucket = 'TABUNG' AND annual_account_id IS NULL AND tabung_account_id IS NOT NULL AND source_year IS NOT NULL)))");
        }
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS leave_reservation_slot_allocations_guard BEFORE INSERT ON leave_reservation_slot_allocations FOR EACH ROW BEGIN
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
ELSEIF NEW.allocation_bucket = 'TABUNG' THEN
SELECT employee_id INTO account_employee FROM leave_tabung_accounts WHERE id = NEW.tabung_account_id;
IF account_employee IS NULL OR account_employee <> reservation_employee OR NEW.tabung_account_id <> reservation_tabung
OR NEW.source_year <> reservation_tabung_source_year THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung slot allocation must use the reservation employee account';
END IF;
ELSE
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unsupported leave slot allocation bucket';
END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS leave_reservation_slots_terminal_guard BEFORE UPDATE ON leave_reservations FOR EACH ROW BEGIN
DECLARE allocation_count BIGINT UNSIGNED DEFAULT 0;
DECLARE annual_units BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_units BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_source_count BIGINT UNSIGNED DEFAULT 0;
DECLARE tabung_source_year SMALLINT UNSIGNED DEFAULT NULL;
DECLARE unmatched_slot_count BIGINT UNSIGNED DEFAULT 0;
IF NEW.status <> OLD.status AND NEW.status IN ('COMMITTED', 'RELEASED', 'CANCELLED')
AND EXISTS (SELECT 1 FROM leave_reservation_slot_allocations WHERE reservation_id = OLD.id) THEN
SELECT COUNT(*),
COALESCE(SUM(CASE WHEN allocation_bucket = 'ANNUAL' THEN units ELSE 0 END), 0),
COALESCE(SUM(CASE WHEN allocation_bucket = 'TABUNG' THEN units ELSE 0 END), 0),
COUNT(DISTINCT CASE WHEN allocation_bucket = 'TABUNG' THEN source_year END),
MIN(CASE WHEN allocation_bucket = 'TABUNG' THEN source_year END)
INTO allocation_count, annual_units, tabung_units, tabung_source_count, tabung_source_year
FROM leave_reservation_slot_allocations WHERE reservation_id = OLD.id;
SELECT COUNT(*) INTO unmatched_slot_count
FROM leave_reservation_slot_allocations AS allocation
LEFT JOIN leave_active_slots AS active_slot
ON active_slot.reservation_id = allocation.reservation_id
AND active_slot.leave_date = allocation.leave_date
AND active_slot.session_code = allocation.session_code
WHERE allocation.reservation_id = OLD.id AND active_slot.id IS NULL;
IF allocation_count <> NEW.requested_units OR allocation_count = 0
OR annual_units <> NEW.annual_units OR tabung_units <> NEW.tabung_units OR unmatched_slot_count <> 0
OR (tabung_units = 0 AND NEW.tabung_source_year IS NOT NULL)
OR (tabung_units > 0 AND (tabung_source_count <> 1 OR tabung_source_year <> NEW.tabung_source_year)) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reservation transition requires a complete matching per-date source map';
END IF;
END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_reservation_slot_allocations_no_update BEFORE UPDATE ON leave_reservation_slot_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot allocations are immutable'");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_reservation_slot_allocations_no_delete BEFORE DELETE ON leave_reservation_slot_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave slot allocations cannot be deleted'");
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_reservation_slot_allocations') && DB::table('leave_reservation_slot_allocations')->exists()) {
            throw new RuntimeException('Per-date leave reservation allocation history is not reversible.');
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slot_allocations_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slot_allocations_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slots_terminal_guard');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_reservation_slot_allocations_guard');
        Schema::dropIfExists('leave_reservation_slot_allocations');
    }
};
