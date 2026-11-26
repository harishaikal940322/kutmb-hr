<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_tabung_cap_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->date('effective_from');
            $table->unsignedInteger('annual_rate_units');
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('operation_key', 96)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['annual_account_id', 'effective_from'], 'ltcc_account_effective_unique');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_tabung_cap_confirmations ADD CONSTRAINT ltcc_values CHECK (annual_rate_units > 0 AND annual_rate_units <= 100000 AND TRIM(reference) <> '')");
            DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_tabung_cap_confirmations_guard BEFORE INSERT ON leave_tabung_cap_confirmations FOR EACH ROW BEGIN
DECLARE matched INT DEFAULT 0;
SELECT COUNT(*) INTO matched FROM leave_annual_accounts AS annual
INNER JOIN leave_annual_entitlement_confirmations AS confirmation ON confirmation.annual_account_id = annual.id
INNER JOIN users AS actor ON actor.id = NEW.confirmed_by
WHERE annual.id = NEW.annual_account_id AND annual.leave_year = YEAR(NEW.effective_from)
AND confirmation.employee_id = annual.employee_id AND confirmation.leave_year = annual.leave_year
AND actor.status = 'ACTIVE' AND actor.username IS NOT NULL;
IF matched <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung rate requires a matching confirmed annual account and active confirmer'; END IF;
END
SQL);
            DB::unprepared("CREATE TRIGGER leave_tabung_cap_confirmations_no_update BEFORE UPDATE ON leave_tabung_cap_confirmations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung rate confirmation is immutable'");
            DB::unprepared("CREATE TRIGGER leave_tabung_cap_confirmations_no_delete BEFORE DELETE ON leave_tabung_cap_confirmations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabung rate confirmation cannot be deleted'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Confirmed Tabung rate history is intentionally not reversible.');
    }
};
