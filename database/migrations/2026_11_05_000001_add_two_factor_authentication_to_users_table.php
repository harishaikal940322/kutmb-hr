<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_used_at')->nullable();
        });

        Schema::create('user_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'code_hash']);
            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recovery_codes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_at',
            ]);
        });
    }
};
