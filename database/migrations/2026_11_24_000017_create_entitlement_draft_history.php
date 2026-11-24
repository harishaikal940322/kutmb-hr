<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_draft_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entitlement_draft_id')->constrained('leave_entitlement_rule_drafts')->restrictOnDelete();
            $table->foreignId('previous_category_id')->constrained('employment_categories')->restrictOnDelete();
            $table->string('previous_band_description');
            $table->unsignedInteger('previous_annual_half_day_units')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entitlement_draft_id', 'created_at'], 'entitlement_draft_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_draft_history');
    }
};
