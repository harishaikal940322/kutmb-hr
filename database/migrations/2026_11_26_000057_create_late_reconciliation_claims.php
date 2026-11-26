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
            throw new RuntimeException('Late reconciliation requires MySQL.');
        }
        Schema::create('leave_late_release_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained('leave_reservations', 'id', 'llrc_reservation_fk')->restrictOnDelete();
            $table->foreignId('release_operation_id')->constrained('leave_balance_operations', 'id', 'llrc_release_fk')->restrictOnDelete();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts', 'id', 'llrc_annual_fk')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts', 'id', 'llrc_tabung_fk')->restrictOnDelete();
            $table->unsignedSmallInteger('source_year');
            $table->unsignedBigInteger('units');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['reservation_id', 'annual_account_id'], 'llrc_reservation_annual_uq');
            $table->unique(['release_operation_id', 'annual_account_id'], 'llrc_release_annual_uq');
        });
        DB::statement('ALTER TABLE leave_late_release_claims ADD CONSTRAINT llrc_units_valid CHECK (units > 0)');
        Schema::create('leave_late_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('source_kind', 16);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('balance_operation_id')->constrained('leave_balance_operations', 'id', 'llr_operation_fk')->restrictOnDelete();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts', 'id', 'llr_annual_fk')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts', 'id', 'llr_tabung_fk')->restrictOnDelete();
            $table->unsignedSmallInteger('source_year');
            $table->unsignedBigInteger('cap_units');
            $table->date('cap_effective_from');
            $table->bigInteger('annual_before');
            $table->unsignedBigInteger('annual_pending');
            $table->unsignedBigInteger('tabung_before');
            $table->unsignedBigInteger('eligible_units');
            $table->unsignedBigInteger('transfer_units');
            $table->unsignedBigInteger('burn_units');
            $table->foreignId('actor_id')->constrained('users', 'id', 'llr_actor_fk')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['source_kind', 'source_id', 'annual_account_id'], 'llr_source_annual_uq');
            $table->unique(['balance_operation_id', 'annual_account_id'], 'llr_operation_annual_uq');
        });
        DB::statement("ALTER TABLE leave_late_reconciliations ADD CONSTRAINT llr_amount_valid CHECK (source_kind IN ('RELEASE','ATTENDANCE','HR_ADJUSTMENT') AND eligible_units > 0 AND annual_before >= annual_pending + eligible_units AND tabung_before <= cap_units AND transfer_units + burn_units = eligible_units AND tabung_before + transfer_units <= cap_units AND transfer_units = LEAST(eligible_units, cap_units - tabung_before))");
        foreach (['leave_late_release_claims' => 'llrc', 'leave_late_reconciliations' => 'llr'] as $table => $prefix) {
            DB::unprepared("CREATE TRIGGER {$prefix}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Late reconciliation history is immutable'");
            DB::unprepared("CREATE TRIGGER {$prefix}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Late reconciliation history cannot be deleted'");
        }
        DB::statement("ALTER TABLE leave_attendance_review_outcomes ADD CONSTRAINT laro_finding_v2 CHECK (finding IN ('ABSENCE_CONFIRMED','ABSENCE_NOT_CONFIRMED','REFERRED','NO_ABSENCE_CONFIRMED'))");
        DB::statement('ALTER TABLE leave_attendance_review_outcomes DROP CHECK laro_finding_valid');
    }

    public function down(): void
    {
        throw new RuntimeException('Late reconciliation history is forward-only.');
    }
};
