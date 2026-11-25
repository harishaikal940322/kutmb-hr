<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared("CREATE TRIGGER leave_ledger_entries_no_update BEFORE UPDATE ON leave_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave ledger entries are append-only'");
        DB::unprepared("CREATE TRIGGER leave_ledger_entries_no_delete BEFORE DELETE ON leave_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Leave ledger entries are append-only'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_entries_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS leave_ledger_entries_no_update');
    }
};
