<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_annual_year_closes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('annual_account_id')->unique()->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('next_annual_account_id')->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('leave_year');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('balance_operation_id')->unique()->constrained('leave_balance_operations')->restrictOnDelete();
            $table->string('operation_key', 96)->unique();
            $table->text('reason');
            $table->unsignedBigInteger('cap_units');
            $table->bigInteger('annual_before');
            $table->unsignedBigInteger('annual_pending');
            $table->bigInteger('tabung_before');
            $table->unsignedBigInteger('transfer_units');
            $table->foreignId('annual_entry_id')->nullable()->unique()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->foreignId('tabung_entry_id')->nullable()->unique()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE leave_annual_year_closes ADD CONSTRAINT layc_transfer_valid CHECK (annual_before >= annual_pending AND tabung_before >= 0 AND transfer_units = annual_before - annual_pending AND tabung_before + transfer_units <= cap_units AND ((transfer_units = 0 AND annual_entry_id IS NULL AND tabung_entry_id IS NULL) OR (transfer_units > 0 AND annual_entry_id IS NOT NULL AND tabung_entry_id IS NOT NULL)))");
            DB::unprepared("CREATE TRIGGER leave_annual_year_closes_no_update BEFORE UPDATE ON leave_annual_year_closes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Year-close history is immutable'");
            DB::unprepared("CREATE TRIGGER leave_annual_year_closes_no_delete BEFORE DELETE ON leave_annual_year_closes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Year-close history cannot be deleted'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Year-close history is intentionally not reversible.');
    }
};
