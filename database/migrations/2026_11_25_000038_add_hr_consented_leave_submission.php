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
            throw new RuntimeException('The internal assisted-submission consent record is verified only for the selected MySQL database.');
        }

        if (! Schema::hasTable('leave_request_on_behalf_submissions')) {
            Schema::create('leave_request_on_behalf_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('request_id')->unique('lrobs_request_uq')
                    ->constrained('leave_requests', 'id', 'lrobs_request_fk')->restrictOnDelete();
                $table->foreignId('applicant_user_id')->constrained('users', 'id', 'lrobs_applicant_fk')->restrictOnDelete();
                $table->foreignId('hr_actor_user_id')->constrained('users', 'id', 'lrobs_hr_actor_fk')->restrictOnDelete();
                $table->text('recording_reason');
                $table->string('consent_evidence_reference', 120);
                $table->timestamp('staff_confirmed_at');
                $table->string('operation_key', 80)->unique('lrobs_operation_uq');
                $table->char('payload_hash', 64);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! DB::table('information_schema.table_constraints')->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'leave_request_on_behalf_submissions')->where('constraint_name', 'lrobs_fields_valid')->exists()) {
            DB::statement("ALTER TABLE leave_request_on_behalf_submissions ADD CONSTRAINT lrobs_fields_valid CHECK (applicant_user_id <> hr_actor_user_id AND TRIM(recording_reason) <> '' AND TRIM(consent_evidence_reference) <> '')");
        }
        DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS leave_request_on_behalf_submissions_before_insert BEFORE INSERT ON leave_request_on_behalf_submissions FOR EACH ROW BEGIN
DECLARE matching_count INT DEFAULT 0;
SELECT COUNT(*) INTO matching_count FROM leave_requests AS request
INNER JOIN employees AS employee ON employee.id = request.employee_id
INNER JOIN users AS applicant ON applicant.id = NEW.applicant_user_id
INNER JOIN users AS hr_actor ON hr_actor.id = NEW.hr_actor_user_id
WHERE request.id = NEW.request_id AND request.requester_user_id = NEW.applicant_user_id
AND employee.user_id = NEW.applicant_user_id AND applicant.status = 'ACTIVE' AND applicant.username IS NOT NULL
AND hr_actor.status = 'ACTIVE' AND hr_actor.username IS NOT NULL
AND NEW.applicant_user_id <> NEW.hr_actor_user_id AND TRIM(NEW.recording_reason) <> ''
AND TRIM(NEW.consent_evidence_reference) <> '' AND NEW.staff_confirmed_at <= CURRENT_TIMESTAMP;
IF matching_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An assisted leave submission requires a distinct active HR actor and linked applicant with prior recorded confirmation'; END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_request_on_behalf_submissions_no_update BEFORE UPDATE ON leave_request_on_behalf_submissions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assisted-submission consent records are immutable'");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS leave_request_on_behalf_submissions_no_delete BEFORE DELETE ON leave_request_on_behalf_submissions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Assisted-submission consent records cannot be deleted'");

        if (app()->environment(['local', 'testing'])) {
            DB::table('permissions')->insertOrIgnore([
                'code' => 'leave.request.record-on-behalf',
                'name' => 'leave.request.record-on-behalf',
                'guard_name' => 'web',
                'label' => 'Record a leave request with staff confirmation',
                'description' => 'Local/testing-only authority boundary for synthetic HR-assisted leave submissions.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Assisted-submission consent history is intentionally not reversible.');
    }
};
