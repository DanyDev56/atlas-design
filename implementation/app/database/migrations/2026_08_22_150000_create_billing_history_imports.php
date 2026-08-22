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
        Schema::create('billing.history_import_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('source_system', 64);
            $table->timestampTz('source_exported_at');
            $table->string('schema_version', 16);
            $table->string('package_hash', 64);
            $table->unsignedInteger('quote_count')->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->unsignedInteger('validation_error_count')->default(0);
            $table->jsonb('quotes');
            $table->jsonb('invoices');
            $table->jsonb('payments');
            $table->jsonb('validation_errors');
            $table->uuid('created_by');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
            $table->index(['workspace_id', 'package_hash']);
        });

        Schema::create('billing.history_import_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('preview_id');
            $table->string('source_system', 64);
            $table->timestampTz('source_exported_at');
            $table->string('package_hash', 64);
            $table->string('status', 32);
            $table->string('checkpoint', 32)->default('Quotes');
            $table->unsignedInteger('quote_count')->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->unsignedInteger('processed_quotes')->default(0);
            $table->unsignedInteger('processed_invoices')->default(0);
            $table->unsignedInteger('processed_payments')->default(0);
            $table->uuid('created_by');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['workspace_id', 'status']);
            $table->unique(['workspace_id', 'package_hash']);
        });

        Schema::table('billing.quotes', function (Blueprint $table): void {
            $this->provenance($table);
            $table->string('original_number')->nullable();
            $table->bigInteger('net_amount_cents')->nullable();
            $table->bigInteger('tax_amount_cents')->nullable();
            $table->bigInteger('gross_amount_cents')->nullable();
            $table->timestampTz('responded_at')->nullable();
        });

        Schema::table('billing.invoices', function (Blueprint $table): void {
            $this->provenance($table);
            $table->string('original_number')->nullable();
            $table->bigInteger('net_amount_cents')->nullable();
            $table->bigInteger('tax_amount_cents')->nullable();
            $table->bigInteger('gross_amount_cents')->nullable();
        });

        Schema::table('billing.payments', function (Blueprint $table): void {
            $this->provenance($table);
            $table->bigInteger('amount_received_cents')->nullable();
            $table->bigInteger('amount_applied_cents')->nullable();
            $table->string('status', 32)->nullable();
        });

        foreach (['quotes', 'invoices', 'payments'] as $table) {
            DB::statement(
                "CREATE UNIQUE INDEX billing_{$table}_historical_identity_unique
                 ON billing.{$table} (workspace_id, source_system, external_id)
                 WHERE source_system IS NOT NULL AND external_id IS NOT NULL",
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing.billing_payments_historical_identity_unique');
        DB::statement('DROP INDEX IF EXISTS billing.billing_invoices_historical_identity_unique');
        DB::statement('DROP INDEX IF EXISTS billing.billing_quotes_historical_identity_unique');

        Schema::table('billing.payments', function (Blueprint $table): void {
            $table->dropColumn([
                'is_historical_import', 'source_system', 'external_id', 'import_run_id',
                'canonical_record_hash', 'source_exported_at', 'amount_received_cents',
                'amount_applied_cents', 'status',
            ]);
        });
        Schema::table('billing.invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'is_historical_import', 'source_system', 'external_id', 'import_run_id',
                'canonical_record_hash', 'source_exported_at', 'original_number',
                'net_amount_cents', 'tax_amount_cents', 'gross_amount_cents',
            ]);
        });
        Schema::table('billing.quotes', function (Blueprint $table): void {
            $table->dropColumn([
                'is_historical_import', 'source_system', 'external_id', 'import_run_id',
                'canonical_record_hash', 'source_exported_at', 'original_number',
                'net_amount_cents', 'tax_amount_cents', 'gross_amount_cents', 'responded_at',
            ]);
        });
        Schema::dropIfExists('billing.history_import_runs');
        Schema::dropIfExists('billing.history_import_previews');
    }

    private function provenance(Blueprint $table): void
    {
        $table->boolean('is_historical_import')->default(false);
        $table->string('source_system', 64)->nullable();
        $table->string('external_id', 160)->nullable();
        $table->uuid('import_run_id')->nullable();
        $table->string('canonical_record_hash', 64)->nullable();
        $table->timestampTz('source_exported_at')->nullable();
    }
};
