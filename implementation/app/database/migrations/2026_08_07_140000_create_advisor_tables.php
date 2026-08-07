<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS advisor');

        if (! $this->tableExists('advisor', 'recommendations')) {
            Schema::create('advisor.recommendations', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('business_health_assessment_id');
                $table->string('recommendation_key', 64);
                $table->string('rule_key', 64);
                $table->string('status', 32);
                $table->string('priority', 16);
                $table->unsignedSmallInteger('rank_score');
                $table->jsonb('action');
                $table->jsonb('expected_impact');
                $table->string('urgency', 16);
                $table->string('confidence', 16);
                $table->string('effort', 16);
                $table->timestampTz('valid_until');
                $table->timestampTz('generated_at');
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! $this->tableExists('advisor', 'overviews')) {
            Schema::create('advisor.overviews', function ($table): void {
                $table->uuid('workspace_id')->primary();
                $table->unsignedInteger('advisor_overview_version');
                $table->string('source_eligibility', 32);
                $table->uuid('business_health_assessment_id')->nullable();
                $table->uuid('primary_recommendation_id')->nullable();
                $table->jsonb('recommendation_ids');
                $table->timestampTz('updated_at');
            });
        }

        if (! $this->tableExists('advisor', 'idempotency_keys')) {
            Schema::create('advisor.idempotency_keys', function ($table): void {
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
        Schema::dropIfExists('advisor.idempotency_keys');
        Schema::dropIfExists('advisor.overviews');
        Schema::dropIfExists('advisor.recommendations');
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
