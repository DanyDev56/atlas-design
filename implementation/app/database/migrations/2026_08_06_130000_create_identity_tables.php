<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS identity');

        if (! $this->tableExists('identity', 'users')) {
            Schema::create('identity.users', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('display_name');
                $table->string('password_hash');
                $table->string('status', 32);
                $table->string('email_verification_status', 32);
                $table->unsignedInteger('security_version')->default(1);
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
            });
        }

        if (! $this->tableExists('identity', 'email_verification_tokens')) {
            Schema::create('identity.email_verification_tokens', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('token_hash')->unique();
                $table->timestampTz('expires_at');
                $table->timestampTz('consumed_at')->nullable();
                $table->timestampTz('created_at');
            });
        }

        if (! $this->tableExists('identity', 'sessions')) {
            Schema::create('identity.sessions', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('token_hash')->unique();
                $table->string('status', 32);
                $table->timestampTz('expires_at');
                $table->timestampTz('created_at');
                $table->timestampTz('revoked_at')->nullable();
            });
        }

        if (! $this->tableExists('identity', 'roles')) {
            Schema::create('identity.roles', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->string('name');
                $table->jsonb('permissions');
                $table->string('status', 32);
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
            });
        }

        if (! $this->tableExists('identity', 'memberships')) {
            Schema::create('identity.memberships', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('workspace_id');
                $table->uuid('role_id');
                $table->string('status', 32);
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->unique(['user_id', 'workspace_id']);
            });
        }

        if (! $this->tableExists('identity', 'idempotency_keys')) {
            Schema::create('identity.idempotency_keys', function ($table): void {
                $table->string('scope');
                $table->string('key');
                $table->string('fingerprint');
                $table->jsonb('response_payload')->nullable();
                $table->timestampTz('created_at');
                $table->primary(['scope', 'key']);
            });
        }

        if (! $this->tableExists('identity', 'bootstrap_workflows')) {
            Schema::create('identity.bootstrap_workflows', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('workspace_id')->nullable();
                $table->string('idempotency_key')->unique();
                $table->string('status', 32);
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.bootstrap_workflows');
        Schema::dropIfExists('identity.idempotency_keys');
        Schema::dropIfExists('identity.memberships');
        Schema::dropIfExists('identity.roles');
        Schema::dropIfExists('identity.sessions');
        Schema::dropIfExists('identity.email_verification_tokens');
        Schema::dropIfExists('identity.users');
    }

    private function tableExists(string $schema, string $table): bool
    {
        $result = DB::selectOne(
            'SELECT to_regclass(?) IS NOT NULL AS exists',
            ["{$schema}.{$table}"],
        );

        return (bool) $result->exists;
    }
};
