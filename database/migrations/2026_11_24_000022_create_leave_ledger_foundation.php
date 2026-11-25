<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_annual_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('leave_year');
            $table->timestamps();
            $table->unique(['employee_id', 'leave_year']);
        });
        Schema::create('leave_tabung_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('leave_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annual_account_id')->nullable()->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->nullable()->constrained('leave_tabung_accounts')->restrictOnDelete();
            $table->bigInteger('signed_half_day_units');
            $table->string('kind', 64);
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->string('operation_key', 120)->unique();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leave_ledger_entries ADD CONSTRAINT leave_ledger_one_account CHECK ((annual_account_id IS NOT NULL AND tabung_account_id IS NULL) OR (annual_account_id IS NULL AND tabung_account_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
        Schema::dropIfExists('leave_tabung_accounts');
        Schema::dropIfExists('leave_annual_accounts');
    }
};
