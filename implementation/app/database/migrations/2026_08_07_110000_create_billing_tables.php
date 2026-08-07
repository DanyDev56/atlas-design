<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS billing');

        if (! $this->tableExists('billing', 'quotes')) {
            Schema::create('billing.quotes', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('client_id');
                $table->uuid('opportunity_id')->nullable();
                $table->string('status', 32);
                $table->jsonb('lines');
                $table->bigInteger('total_cents');
                $table->string('currency', 3)->default('EUR');
                $table->jsonb('client_snapshot');
                $table->jsonb('opportunity_snapshot')->nullable();
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->timestampTz('sent_at')->nullable();
                $table->timestampTz('accepted_at')->nullable();
                $table->timestampTz('valid_until')->nullable();
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! $this->tableExists('billing', 'invoices')) {
            Schema::create('billing.invoices', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('client_id');
                $table->uuid('quote_id')->nullable();
                $table->string('status', 32);
                $table->string('settlement_status', 32);
                $table->string('invoice_number')->nullable();
                $table->jsonb('lines');
                $table->bigInteger('total_cents');
                $table->bigInteger('balance_cents');
                $table->string('currency', 3)->default('EUR');
                $table->jsonb('client_snapshot');
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->timestampTz('issued_at')->nullable();
                $table->timestampTz('sent_at')->nullable();
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! $this->tableExists('billing', 'payments')) {
            Schema::create('billing.payments', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('invoice_id');
                $table->bigInteger('amount_cents');
                $table->string('currency', 3);
                $table->string('reference')->nullable();
                $table->timestampTz('recorded_at');
                $table->timestampTz('created_at');
                $table->index(['invoice_id']);
            });
        }

        if (! $this->tableExists('billing', 'public_document_proofs')) {
            Schema::create('billing.public_document_proofs', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->string('document_type', 32);
                $table->uuid('document_id');
                $table->string('token_hash')->unique();
                $table->jsonb('capabilities');
                $table->timestampTz('expires_at');
                $table->timestampTz('consumed_at')->nullable();
                $table->timestampTz('created_at');
            });
        }

        if (! $this->tableExists('billing', 'idempotency_keys')) {
            Schema::create('billing.idempotency_keys', function ($table): void {
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
        Schema::dropIfExists('billing.idempotency_keys');
        Schema::dropIfExists('billing.public_document_proofs');
        Schema::dropIfExists('billing.payments');
        Schema::dropIfExists('billing.invoices');
        Schema::dropIfExists('billing.quotes');
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
