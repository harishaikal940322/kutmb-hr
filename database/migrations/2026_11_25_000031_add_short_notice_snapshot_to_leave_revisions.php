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
            throw new RuntimeException('Leave notice snapshots are verified only for the selected MySQL database.');
        }

        Schema::table('leave_request_revisions', function (Blueprint $table): void {
            $table->boolean('is_short_notice_emergency')->default(false);
            $table->unsignedTinyInteger('notice_working_days_min')->nullable();
            $table->unsignedTinyInteger('notice_working_days_max')->nullable();
        });

        DB::statement('ALTER TABLE leave_request_revisions ADD CONSTRAINT lrrev_notice_range_valid CHECK ((notice_working_days_min IS NULL AND notice_working_days_max IS NULL) OR (notice_working_days_min IS NOT NULL AND notice_working_days_max IS NOT NULL AND notice_working_days_min <= notice_working_days_max AND notice_working_days_max < 3))');
        DB::statement('ALTER TABLE leave_request_revisions ADD CONSTRAINT lrrev_short_notice_reason_valid CHECK (is_short_notice_emergency = 0 OR (notice_working_days_min IS NOT NULL AND notice_working_days_max IS NOT NULL AND notice_working_days_max < 3))');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leave_request_revisions DROP CHECK lrrev_short_notice_reason_valid');
            DB::statement('ALTER TABLE leave_request_revisions DROP CHECK lrrev_notice_range_valid');
        }

        Schema::table('leave_request_revisions', function (Blueprint $table): void {
            $table->dropColumn(['is_short_notice_emergency', 'notice_working_days_min', 'notice_working_days_max']);
        });
    }
};
