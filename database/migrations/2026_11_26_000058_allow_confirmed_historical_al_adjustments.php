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
            throw new RuntimeException('Historical adjustment reconciliation requires MySQL.');
        }
        Schema::table('leave_balance_adjustments', function (Blueprint $table): void {
            $table->boolean('historical_eligible')->default(false);
            $table->boolean('historical_verified')->default(false);
        });
        DB::statement("ALTER TABLE leave_balance_adjustments ADD CONSTRAINT lba_historical_valid CHECK ((historical_eligible = 0 AND historical_verified = 0) OR (historical_eligible = 1 AND bucket = 'AL' AND source_year IS NULL AND signed_units > 0 AND ((status = 'APPROVED' AND historical_verified = 1) OR (status IN ('PENDING','REJECTED') AND historical_verified = 0))))");
        DB::unprepared('DROP TRIGGER IF EXISTS leave_adjustments_transition_guard');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER leave_adjustments_transition_guard BEFORE UPDATE ON leave_balance_adjustments FOR EACH ROW
BEGIN
IF OLD.status <> 'PENDING' OR NEW.status NOT IN ('APPROVED','REJECTED')
OR NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.annual_account_id <=> NEW.annual_account_id)
OR NOT (OLD.tabung_account_id <=> NEW.tabung_account_id) OR NOT (OLD.leave_year <=> NEW.leave_year)
OR NOT (OLD.historical_eligible <=> NEW.historical_eligible) OR NOT (OLD.bucket <=> NEW.bucket) OR NOT (OLD.source_year <=> NEW.source_year)
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

    public function down(): void
    {
        throw new RuntimeException('Historical adjustment attestations are forward-only.');
    }
};
