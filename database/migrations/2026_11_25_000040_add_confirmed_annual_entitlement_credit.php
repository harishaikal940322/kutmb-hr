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
            throw new RuntimeException('Annual entitlement confirmations and credits are verified only for the selected MySQL database.');
        }

        if (! Schema::hasTable('leave_annual_entitlement_confirmations')) {
            Schema::create('leave_annual_entitlement_confirmations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('annual_account_id')->unique('laec_account_uq')
                    ->constrained('leave_annual_accounts', 'id', 'laec_account_fk')->restrictOnDelete();
                $table->foreignId('employee_id')->constrained('employees', 'id', 'laec_employee_fk')->restrictOnDelete();
                $table->unsignedSmallInteger('leave_year');
                $table->unsignedBigInteger('confirmed_half_day_units');
                $table->string('confirmation_reference', 120);
                $table->foreignId('confirmed_by')->constrained('users', 'id', 'laec_confirmer_fk')->restrictOnDelete();
                $table->string('confirmation_key', 80)->unique('laec_key_uq');
                $table->char('payload_hash', 64);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! DB::table('information_schema.table_constraints')->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'leave_annual_entitlement_confirmations')->where('constraint_name', 'laec_values_valid')->exists()) {
            DB::statement("ALTER TABLE leave_annual_entitlement_confirmations ADD CONSTRAINT laec_values_valid CHECK (confirmed_half_day_units > 0 AND TRIM(confirmation_reference) <> '' AND payload_hash REGEXP '^[a-f0-9]{64}$')");
        }

        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS leave_annual_entitlement_confirmations_before_insert BEFORE INSERT ON leave_annual_entitlement_confirmations FOR EACH ROW BEGIN
DECLARE matching_count INT DEFAULT 0;
SELECT COUNT(*) INTO matching_count FROM leave_annual_accounts AS annual
INNER JOIN employees AS employee ON employee.id = annual.employee_id
INNER JOIN users AS confirmer ON confirmer.id = NEW.confirmed_by
WHERE annual.id = NEW.annual_account_id AND annual.employee_id = NEW.employee_id
AND annual.leave_year = NEW.leave_year AND confirmer.status = 'ACTIVE' AND confirmer.username IS NOT NULL
AND NEW.confirmed_half_day_units > 0 AND TRIM(NEW.confirmation_reference) <> ''
AND NEW.payload_hash REGEXP '^[a-f0-9]{64}$';
IF matching_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An entitlement confirmation must match an annual account, year, active HR confirmer and explicit positive amount'; END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_annual_entitlement_confirmations_no_update BEFORE UPDATE ON leave_annual_entitlement_confirmations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Annual entitlement confirmations are immutable'");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_annual_entitlement_confirmations_no_delete BEFORE DELETE ON leave_annual_entitlement_confirmations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Annual entitlement confirmations cannot be deleted'");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS leave_annual_credit_ledger_guard BEFORE INSERT ON leave_ledger_entries FOR EACH ROW BEGIN
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

        if (app()->environment(['local', 'testing'])) {
            DB::table('permissions')->insertOrIgnore([
                [
                    'code' => 'leave.entitlement.confirm',
                    'name' => 'leave.entitlement.confirm',
                    'guard_name' => 'web',
                    'label' => 'Confirm synthetic annual entitlement input',
                    'description' => 'Local/testing-only authority boundary for explicit entitlement confirmation; grants no rate calculation.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'leave.entitlement.credit',
                    'name' => 'leave.entitlement.credit',
                    'guard_name' => 'web',
                    'label' => 'Post confirmed annual entitlement',
                    'description' => 'Local/testing-only authority boundary for the once-only January 1 annual entitlement credit.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Confirmed entitlement and credit history is intentionally not reversible.');
    }
};
