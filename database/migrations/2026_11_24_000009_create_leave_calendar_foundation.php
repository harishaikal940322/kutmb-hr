<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_calendar_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('leave_calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_version_id')->constrained('leave_calendar_versions')->restrictOnDelete();
            $table->date('date');
            $table->string('name');
            $table->string('kind', 32);
            $table->foreignId('original_day_id')->nullable()->constrained('leave_calendar_days')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['calendar_version_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_calendar_days');
        Schema::dropIfExists('leave_calendar_versions');
    }
};
