<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('active');
            $table->timestamps();
        });

        Schema::create('employment_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('active');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_no', 64)->unique();
            $table->string('display_name');
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->date('service_start');
            $table->date('confirmation_date')->nullable();
            $table->date('service_end')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('employment_categories')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
            $table->index(['unit_id', 'effective_from']);
        });

        Schema::create('approval_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('office_code', 32);
            $table->foreignId('unit_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['office_code', 'unit_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_assignments');
        Schema::dropIfExists('employee_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employment_categories');
        Schema::dropIfExists('organization_units');
    }
};
