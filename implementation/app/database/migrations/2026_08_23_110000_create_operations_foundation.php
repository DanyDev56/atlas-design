<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS operations');

        Schema::create('operations.operator_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->jsonb('permissions');
            $table->string('status', 32);
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('revoked_at')->nullable();
        });

        Schema::create('operations.operator_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('grant_id');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 32);
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->index(['user_id', 'status']);
            $table->index(['grant_id', 'status']);
        });

        Schema::create('operations.operator_audit_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operator_user_id')->nullable();
            $table->uuid('operator_session_id')->nullable();
            $table->string('action', 96);
            $table->string('permission', 96)->nullable();
            $table->string('result', 32);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id_hash', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('metadata');
            $table->timestampTz('occurred_at');
            $table->index(['operator_user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index(['result', 'occurred_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION operations.reject_operator_audit_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'operations.operator_audit_entries is append-only';
            END;
            $$;

            CREATE TRIGGER operations_operator_audit_append_only
            BEFORE UPDATE OR DELETE ON operations.operator_audit_entries
            FOR EACH ROW
            EXECUTE FUNCTION operations.reject_operator_audit_mutation();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.operator_audit_entries');
        DB::statement('DROP FUNCTION IF EXISTS operations.reject_operator_audit_mutation()');
        Schema::dropIfExists('operations.operator_sessions');
        Schema::dropIfExists('operations.operator_grants');
    }
};
