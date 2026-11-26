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
            throw new RuntimeException('Partial refund reconciliation requires MySQL.');
        }
        Schema::create('leave_partial_refund_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partial_cancellation_id')->constrained('leave_request_partial_cancellations', 'id', 'lprr_case_fk')->restrictOnDelete();
            $table->foreignId('balance_operation_id')->constrained('leave_balance_operations', 'id', 'lprr_operation_fk')->restrictOnDelete();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts', 'id', 'lprr_annual_fk')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts', 'id', 'lprr_tabung_fk')->restrictOnDelete();
            $table->unsignedSmallInteger('source_year');
            $table->unsignedBigInteger('cap_units');
            $table->date('cap_effective_from');
            $table->bigInteger('annual_before');
            $table->unsignedBigInteger('annual_pending');
            $table->unsignedBigInteger('tabung_before');
            $table->unsignedBigInteger('eligible_units');
            $table->unsignedBigInteger('transfer_units');
            $table->unsignedBigInteger('burn_units');
            $table->foreignId('actor_id')->constrained('users', 'id', 'lprr_actor_fk')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['partial_cancellation_id', 'annual_account_id'], 'lprr_case_annual_uq');
            $table->unique(['balance_operation_id', 'annual_account_id'], 'lprr_operation_annual_uq');
        });
        DB::statement("ALTER TABLE leave_partial_refund_reconciliations ADD CONSTRAINT lprr_amount_valid CHECK (eligible_units > 0 AND annual_before >= annual_pending AND tabung_before <= cap_units AND transfer_units + burn_units = eligible_units AND tabung_before + transfer_units <= cap_units AND transfer_units = LEAST(eligible_units, cap_units - tabung_before))");
        DB::unprepared("CREATE TRIGGER leave_partial_refund_reconciliations_no_update BEFORE UPDATE ON leave_partial_refund_reconciliations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial refund reconciliation history is immutable'");
        DB::unprepared("CREATE TRIGGER leave_partial_refund_reconciliations_no_delete BEFORE DELETE ON leave_partial_refund_reconciliations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partial refund reconciliation history cannot be deleted'");
    }

    public function down(): void
    {
        throw new RuntimeException('Partial refund reconciliation history is forward-only.');
    }
};
