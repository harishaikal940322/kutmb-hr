<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balance_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('leave_year');
            $table->string('bucket', 8);
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->bigInteger('signed_units');
            $table->text('reason');
            $table->string('evidence_reference', 120);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->string('submission_key', 36)->unique();
            $table->char('payload_hash', 64);
            $table->bigInteger('prepared_balance');
            $table->bigInteger('prepared_pending');
            $table->string('status', 16)->default('PENDING');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_reason')->nullable();
            $table->string('decision_key', 36)->nullable()->unique();
            $table->timestamp('decided_at')->nullable();
            $table->bigInteger('before_units')->nullable();
            $table->bigInteger('after_units')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->unique()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->timestamps();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_balance_adjustments ADD CONSTRAINT lba_bucket_valid CHECK (signed_units <> 0 AND ((bucket = 'AL' AND source_year IS NULL) OR (bucket = 'TABUNG' AND source_year IS NOT NULL)))");
            DB::statement("ALTER TABLE leave_balance_adjustments ADD CONSTRAINT lba_state_valid CHECK ((status = 'PENDING' AND decided_by IS NULL AND decision_key IS NULL AND decided_at IS NULL AND ledger_entry_id IS NULL AND before_units IS NULL AND after_units IS NULL) OR (status IN ('APPROVED','REJECTED') AND decided_by IS NOT NULL AND decided_by <> prepared_by AND decision_key IS NOT NULL AND decided_at IS NOT NULL AND decision_reason IS NOT NULL AND ((status = 'APPROVED' AND ledger_entry_id IS NOT NULL AND before_units IS NOT NULL AND after_units IS NOT NULL) OR (status = 'REJECTED' AND ledger_entry_id IS NULL AND before_units IS NULL AND after_units IS NULL))))");
            DB::unprepared("CREATE TRIGGER leave_adjustments_no_delete BEFORE DELETE ON leave_balance_adjustments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Adjustment history cannot be deleted'");
            DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_adjustments_transition_guard BEFORE UPDATE ON leave_balance_adjustments FOR EACH ROW
BEGIN
IF OLD.status <> 'PENDING' OR NEW.status NOT IN ('APPROVED','REJECTED')
OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.annual_account_id <=> NEW.annual_account_id)
OR NOT (OLD.tabung_account_id <=> NEW.tabung_account_id) OR NOT (OLD.leave_year <=> NEW.leave_year)
OR NOT (OLD.bucket <=> NEW.bucket) OR NOT (OLD.source_year <=> NEW.source_year)
OR NOT (OLD.signed_units <=> NEW.signed_units) OR NOT (OLD.reason <=> NEW.reason)
OR NOT (OLD.evidence_reference <=> NEW.evidence_reference) OR NOT (OLD.prepared_by <=> NEW.prepared_by)
OR NOT (OLD.submission_key <=> NEW.submission_key) OR NOT (OLD.payload_hash <=> NEW.payload_hash)
OR NOT (OLD.prepared_balance <=> NEW.prepared_balance) OR NOT (OLD.prepared_pending <=> NEW.prepared_pending)
OR NOT (OLD.created_at <=> NEW.created_at) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one independent terminal adjustment decision is allowed';
END IF;
END
SQL);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Adjustment history is intentionally not reversible.');
    }
};
