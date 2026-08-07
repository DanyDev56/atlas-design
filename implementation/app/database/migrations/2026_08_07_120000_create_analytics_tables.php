<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS analytics');

        if (! $this->tableExists('analytics', 'source_facts')) {
            Schema::create('analytics.source_facts', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('source_event_id');
                $table->string('source_event_type', 64);
                $table->string('aggregate_type', 32);
                $table->uuid('aggregate_id');
                $table->unsignedInteger('aggregate_version');
                $table->string('fact_hash', 64);
                $table->jsonb('payload');
                $table->timestampTz('source_occurred_at');
                $table->timestampTz('ingested_at');
                $table->unique(['workspace_id', 'source_event_id']);
                $table->index(['workspace_id', 'aggregate_type', 'aggregate_id']);
            });
        }

        if (! $this->tableExists('analytics', 'watermarks')) {
            Schema::create('analytics.watermarks', function ($table): void {
                $table->uuid('workspace_id');
                $table->string('source_kind', 16);
                $table->timestampTz('complete_through');
                $table->timestampTz('updated_at');
                $table->primary(['workspace_id', 'source_kind']);
            });
        }

        if (! $this->tableExists('analytics', 'observations')) {
            Schema::create('analytics.observations', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->unsignedInteger('generation_id');
                $table->string('metric_key', 64);
                $table->string('window_kind', 32);
                $table->string('currency', 3)->nullable();
                $table->string('value_status', 16);
                $table->bigInteger('value_cents')->nullable();
                $table->jsonb('value_json')->nullable();
                $table->timestampTz('as_of');
                $table->timestampTz('calculated_at');
                $table->index(['workspace_id', 'generation_id']);
            });
        }

        if (! $this->tableExists('analytics', 'snapshots')) {
            Schema::create('analytics.snapshots', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->string('profile_key', 64);
                $table->string('profile_version', 16);
                $table->unsignedInteger('generation_id');
                $table->timestampTz('as_of');
                $table->string('freshness_status', 16);
                $table->string('completeness_status', 16);
                $table->jsonb('metrics');
                $table->jsonb('watermarks');
                $table->timestampTz('published_at');
                $table->index(['workspace_id', 'published_at']);
            });
        }

        if (! $this->tableExists('analytics', 'idempotency_keys')) {
            Schema::create('analytics.idempotency_keys', function ($table): void {
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
        Schema::dropIfExists('analytics.idempotency_keys');
        Schema::dropIfExists('analytics.snapshots');
        Schema::dropIfExists('analytics.observations');
        Schema::dropIfExists('analytics.watermarks');
        Schema::dropIfExists('analytics.source_facts');
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
