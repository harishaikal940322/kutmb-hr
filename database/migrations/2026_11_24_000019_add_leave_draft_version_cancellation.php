<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['leave_calendar_versions', 'leave_policy_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['leave_calendar_versions', 'leave_policy_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cancelled_by');
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
