<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics.projection_generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->unsignedInteger('generation_id');
            $table->string('status', 32);
            $table->string('rebuild_reason', 64);
            $table->uuid('crm_import_run_id')->nullable();
            $table->uuid('billing_import_run_id')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unique(['workspace_id', 'generation_id']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('analytics.historical_import_completions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('source_system', 64);
            $table->string('kind', 16);
            $table->uuid('import_run_id');
            $table->string('package_hash', 64);
            $table->timestampTz('completed_at');
            $table->unique(
                ['workspace_id', 'source_system', 'kind', 'import_run_id'],
                'analytics_hist_import_completions_unique',
            );
        });

        Schema::create('analytics.historical_import_rebuilds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('source_system', 64);
            $table->uuid('crm_import_run_id');
            $table->uuid('billing_import_run_id');
            $table->uuid('generation_uuid');
            $table->unsignedInteger('generation_id');
            $table->string('status', 32);
            $table->unsignedInteger('quote_fact_count')->default(0);
            $table->unsignedInteger('invoice_fact_count')->default(0);
            $table->unsignedInteger('payment_fact_count')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(
                ['workspace_id', 'crm_import_run_id', 'billing_import_run_id'],
                'analytics_hist_import_rebuilds_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics.historical_import_rebuilds');
        Schema::dropIfExists('analytics.historical_import_completions');
        Schema::dropIfExists('analytics.projection_generations');
    }
};
