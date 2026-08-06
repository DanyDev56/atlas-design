<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS platform');

        if (! $this->tableExists('platform', 'outbox_messages')) {
            Schema::create('platform.outbox_messages', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->unique();
                $table->string('event_type');
                $table->jsonb('payload');
                $table->timestampTz('occurred_at');
                $table->uuid('correlation_id')->nullable();
                $table->uuid('causation_id')->nullable();
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->timestampTz('created_at');
                $table->timestampTz('dispatched_at')->nullable();
            });
        }

        if (! $this->tableExists('platform', 'inbox_receipts')) {
            Schema::create('platform.inbox_receipts', function ($table): void {
                $table->string('consumer_name');
                $table->uuid('event_id');
                $table->timestampTz('processed_at');
                $table->primary(['consumer_name', 'event_id']);
            });
        }

        if (! $this->tableExists('platform', 'spike_consumer_effects')) {
            Schema::create('platform.spike_consumer_effects', function ($table): void {
                $table->string('consumer_name')->primary();
                $table->unsignedInteger('effect_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform.spike_consumer_effects');
        Schema::dropIfExists('platform.inbox_receipts');
        Schema::dropIfExists('platform.outbox_messages');
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
