<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_annual_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('leave_reservations')->restrictOnDelete();
            $table->foreignId('annual_account_id')->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('approval_operation_id')->unique()->constrained('leave_balance_operations')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('units');
            $table->string('status', 16)->default('COMMITTED');
            $table->foreignId('settled_ledger_entry_id')->nullable()->unique()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->foreignId('cancel_operation_id')->nullable()->unique()->constrained('leave_balance_operations')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->index(['annual_account_id', 'status']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_annual_commitments ADD CONSTRAINT lac_state_valid CHECK (units > 0 AND ((status = 'COMMITTED' AND settled_ledger_entry_id IS NULL AND cancel_operation_id IS NULL AND resolved_at IS NULL) OR (status = 'SETTLED' AND settled_ledger_entry_id IS NOT NULL AND cancel_operation_id IS NULL AND resolved_at IS NOT NULL) OR (status = 'CANCELLED' AND settled_ledger_entry_id IS NULL AND cancel_operation_id IS NOT NULL AND resolved_at IS NOT NULL)))");
            DB::unprepared("CREATE TRIGGER leave_annual_commitments_no_delete BEFORE DELETE ON leave_annual_commitments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Commitment history cannot be deleted'");
            DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_annual_commitments_transition_guard BEFORE UPDATE ON leave_annual_commitments FOR EACH ROW
BEGIN
IF OLD.status <> 'COMMITTED' OR NEW.status NOT IN ('SETTLED','CANCELLED')
OR NOT (OLD.reservation_id <=> NEW.reservation_id) OR NOT (OLD.annual_account_id <=> NEW.annual_account_id)
OR NOT (OLD.approval_operation_id <=> NEW.approval_operation_id) OR NOT (OLD.approved_by <=> NEW.approved_by)
OR NOT (OLD.units <=> NEW.units) OR NOT (OLD.created_at <=> NEW.created_at) THEN
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one commitment settlement or cancellation is allowed';
END IF;
END
SQL);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Commitment history is intentionally not reversible.');
    }
};
