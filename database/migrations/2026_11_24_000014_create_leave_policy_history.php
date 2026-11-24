<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policy_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('leave_policy_versions')->restrictOnDelete();
            $table->json('previous_state');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['policy_version_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_history');
    }
};
