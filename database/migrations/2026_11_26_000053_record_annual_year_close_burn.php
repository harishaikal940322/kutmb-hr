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
            throw new RuntimeException('Year close history requires MySQL.');
        }
        Schema::table('leave_annual_year_closes', function (Blueprint $table): void {
            $table->unsignedBigInteger('burn_units')->default(0);
            $table->foreignId('burn_entry_id')->nullable()->unique()->constrained('leave_ledger_entries')->restrictOnDelete();
            $table->unsignedBigInteger('tabung_disposal_units')->default(0);
        });
        DB::statement("ALTER TABLE leave_annual_year_closes ADD CONSTRAINT layc_close_totals_v2 CHECK (annual_before >= annual_pending AND tabung_before >= 0 AND transfer_units + burn_units = annual_before - annual_pending AND tabung_disposal_units <= tabung_before AND tabung_before - tabung_disposal_units + transfer_units <= cap_units AND ((transfer_units = 0 AND annual_entry_id IS NULL AND tabung_entry_id IS NULL) OR (transfer_units > 0 AND annual_entry_id IS NOT NULL AND tabung_entry_id IS NOT NULL)) AND ((burn_units = 0 AND burn_entry_id IS NULL) OR (burn_units > 0 AND burn_entry_id IS NOT NULL)))");
        DB::statement('ALTER TABLE leave_annual_year_closes DROP CHECK layc_transfer_valid');
    }

    public function down(): void
    {
        throw new RuntimeException('Posted year-close history is forward-only.');
    }
};
