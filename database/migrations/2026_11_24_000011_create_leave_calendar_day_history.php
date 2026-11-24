<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_calendar_day_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_day_id')->constrained('leave_calendar_days')->restrictOnDelete();
            $table->date('previous_date');
            $table->string('previous_name');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['calendar_day_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_calendar_day_history');
    }
};
