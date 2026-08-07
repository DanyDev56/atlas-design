<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS business_health');

        if (! $this->tableExists('business_health', 'assessments')) {
            Schema::create('business_health.assessments', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('analytics_snapshot_id');
                $table->string('health_policy_version', 16);
                $table->timestampTz('as_of');
                $table->timestampTz('assessed_at');
                $table->string('assessment_status', 32);
                $table->string('assessment_reliability', 32);
                $table->unsignedSmallInteger('overall_score')->nullable();
                $table->string('health_band', 16)->nullable();
                $table->string('health_trend', 16)->default('Unknown');
                $table->jsonb('primary_attention')->nullable();
                $table->jsonb('components');
                $table->jsonb('factors');
                $table->jsonb('risks');
                $table->unsignedSmallInteger('global_coverage_percent');
                $table->jsonb('source_fact_summary');
                $table->string('assessment_currency', 3)->nullable();
                $table->timestampTz('source_published_at');
                $table->unique(['workspace_id', 'analytics_snapshot_id', 'health_policy_version'], 'bh_assessments_natural_key');
                $table->index(['workspace_id', 'as_of']);
            });
        }

        if (! $this->tableExists('business_health', 'current_assessments')) {
            Schema::create('business_health.current_assessments', function ($table): void {
                $table->uuid('workspace_id');
                $table->string('health_policy_version', 16);
                $table->uuid('business_health_assessment_id');
                $table->timestampTz('as_of');
                $table->timestampTz('source_published_at');
                $table->primary(['workspace_id', 'health_policy_version']);
            });
        }

        if (! $this->tableExists('business_health', 'idempotency_keys')) {
            Schema::create('business_health.idempotency_keys', function ($table): void {
                $table->string('scope');
                $table->string('key');
                $table->string('fingerprint');
                $table->jsonb('response_payload')->nullable();
                $table->timestampTz('created_at');
                $table->primary(['scope', 'key']);
            });
        }

        if (! $this->tableExists('analytics', 'snapshots')) {
            return;
        }

        if (! Schema::hasColumn('analytics.snapshots', 'source_fact_summary')) {
            Schema::table('analytics.snapshots', function ($table): void {
                $table->jsonb('source_fact_summary')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('analytics.snapshots', 'source_fact_summary')) {
            Schema::table('analytics.snapshots', function ($table): void {
                $table->dropColumn('source_fact_summary');
            });
        }

        Schema::dropIfExists('business_health.idempotency_keys');
        Schema::dropIfExists('business_health.current_assessments');
        Schema::dropIfExists('business_health.assessments');
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
