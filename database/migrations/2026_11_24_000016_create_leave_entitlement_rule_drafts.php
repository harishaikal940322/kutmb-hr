<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlement_rule_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('leave_policy_versions')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('employment_categories')->restrictOnDelete();
            $table->string('band_description');
            $table->unsignedInteger('annual_half_day_units')->nullable();
            $table->timestamps();
            $table->unique(['policy_version_id', 'category_id', 'band_description'], 'leave_entitlement_draft_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_rule_drafts');
    }
};
