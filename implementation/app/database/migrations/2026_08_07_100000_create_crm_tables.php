<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS crm');

        if (! $this->tableExists('crm', 'clients')) {
            Schema::create('crm.clients', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->string('kind', 32);
                $table->string('display_name');
                $table->jsonb('profile');
                $table->jsonb('billing_profile');
                $table->string('status', 32);
                $table->uuid('primary_contact_id')->nullable();
                $table->unsignedInteger('profile_version');
                $table->unsignedInteger('billing_profile_version');
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! $this->tableExists('crm', 'contacts')) {
            Schema::create('crm.contacts', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('client_id');
                $table->jsonb('profile');
                $table->string('status', 32);
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->index(['client_id', 'status']);
            });
        }

        if (! $this->tableExists('crm', 'opportunities')) {
            Schema::create('crm.opportunities', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('client_id');
                $table->uuid('contact_id')->nullable();
                $table->string('title');
                $table->bigInteger('estimated_amount_cents')->nullable();
                $table->string('currency', 3)->default('EUR');
                $table->string('status', 32);
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->timestampTz('qualified_at')->nullable();
                $table->index(['workspace_id', 'status']);
                $table->index(['client_id']);
            });
        }

        if (! $this->tableExists('crm', 'idempotency_keys')) {
            Schema::create('crm.idempotency_keys', function ($table): void {
                $table->string('scope');
                $table->string('key');
                $table->string('fingerprint');
                $table->jsonb('response_payload')->nullable();
                $table->timestampTz('created_at');
                $table->primary(['scope', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm.idempotency_keys');
        Schema::dropIfExists('crm.opportunities');
        Schema::dropIfExists('crm.contacts');
        Schema::dropIfExists('crm.clients');
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
