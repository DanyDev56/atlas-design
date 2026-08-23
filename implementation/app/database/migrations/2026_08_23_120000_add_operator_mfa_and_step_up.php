<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations.operator_mfa_credentials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->text('secret_ciphertext');
            $table->jsonb('recovery_code_hashes');
            $table->string('status', 32);
            $table->unsignedBigInteger('last_used_timestep')->nullable();
            $table->timestampTz('enrolled_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('disabled_at')->nullable();
        });

        Schema::table('operations.operator_sessions', function (Blueprint $table): void {
            $table->string('authentication_strength', 32)->default('PasswordOnly');
            $table->timestampTz('mfa_verified_at')->nullable();
            $table->timestampTz('step_up_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('operations.operator_sessions', function (Blueprint $table): void {
            $table->dropColumn(['authentication_strength', 'mfa_verified_at', 'step_up_at']);
        });

        Schema::dropIfExists('operations.operator_mfa_credentials');
    }
};
