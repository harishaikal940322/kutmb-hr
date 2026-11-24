<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('working_weekdays')->nullable();
            $table->time('work_start')->nullable();
            $table->time('session_boundary')->nullable();
            $table->time('work_end')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_versions');
    }
};
