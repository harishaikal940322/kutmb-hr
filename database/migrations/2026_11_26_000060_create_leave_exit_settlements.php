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
            throw new RuntimeException('Exit settlement requires MySQL.');
        }
        Schema::create('leave_exit_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('annual_account_id')->unique()->constrained('leave_annual_accounts')->restrictOnDelete();
            $table->foreignId('tabung_account_id')->constrained('leave_tabung_accounts')->restrictOnDelete();
            $table->date('exit_date');
            $table->unsignedBigInteger('credited_units');
            $table->unsignedBigInteger('final_entitlement_units');
            $table->bigInteger('annual_before');
            $table->unsignedBigInteger('tabung_before');
            $table->unsignedBigInteger('deficit_units');
            $table->unsignedBigInteger('offset_units');
            $table->unsignedBigInteger('residual_units');
            $table->json('snapshot');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->string('evidence_reference', 120);
            $table->text('reason');
            $table->uuid('operation_key')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE leave_exit_settlements ADD CONSTRAINT les_amount_valid CHECK (credited_units >= final_entitlement_units AND deficit_units > 0 AND offset_units <= tabung_before AND offset_units + residual_units = deficit_units AND deficit_units = credited_units - final_entitlement_units - annual_before)');
        Schema::create('leave_exit_settlement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('leave_exit_settlements')->restrictOnDelete();
            $table->string('event_code', 16);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_key')->unique();
            $table->foreignId('balance_operation_id')->nullable()->unique()->constrained('leave_balance_operations')->restrictOnDelete();
            $table->string('external_reference', 120)->nullable();
            $table->date('settlement_date')->nullable();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['settlement_id', 'event_code'], 'lese_case_event_uq');
        });
        DB::statement("ALTER TABLE leave_exit_settlement_events ADD CONSTRAINT lese_shape CHECK ((event_code = 'STARTED' AND balance_operation_id IS NOT NULL AND external_reference IS NULL AND settlement_date IS NULL) OR (event_code = 'EVIDENCE' AND balance_operation_id IS NULL AND external_reference IS NOT NULL AND settlement_date IS NOT NULL) OR (event_code = 'SETTLED' AND balance_operation_id IS NULL AND external_reference IS NULL AND settlement_date IS NULL))");
        foreach (['leave_exit_settlements' => 'les', 'leave_exit_settlement_events' => 'lese'] as $table => $prefix) {
            DB::unprepared("CREATE TRIGGER {$prefix}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Exit settlement history is immutable'");
            DB::unprepared("CREATE TRIGGER {$prefix}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Exit settlement history cannot be deleted'");
        }
        if (app()->environment(['local', 'testing'])) {
            foreach (['prepare' => 'Sediakan', 'confirm' => 'Sahkan'] as $action => $label) {
                DB::table('permissions')->insertOrIgnore([
                    'code' => 'leave.settlement.'.$action, 'name' => 'leave.settlement.'.$action,
                    'guard_name' => 'web', 'label' => $label.' penyelesaian baki',
                    'description' => 'Local exit settlement '.$action.' permission.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Exit settlement history is forward-only.');
    }
};
