<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations.http_red_minute_buckets', function ($table): void {
            $table->timestampTz('bucket_started_at');
            $table->string('method', 8);
            $table->string('route_template', 180);
            $table->string('status_class', 3);
            $table->unsignedBigInteger('request_count');
            $table->unsignedBigInteger('error_count');
            $table->unsignedBigInteger('duration_sum_ms');
            $table->unsignedInteger('duration_max_ms');
            $table->timestampTz('updated_at');
            $table->primary(['bucket_started_at', 'method', 'route_template', 'status_class']);
            $table->index('bucket_started_at');
        });

        Schema::create('operations.alert_states', function ($table): void {
            $table->string('alert_key', 80)->primary();
            $table->string('state', 16);
            $table->char('fingerprint', 64);
            $table->jsonb('context');
            $table->timestampTz('first_detected_at')->nullable();
            $table->timestampTz('last_evaluated_at');
            $table->timestampTz('last_notified_at')->nullable();
            $table->string('last_notification_state', 16)->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('updated_at');
            $table->index(['state', 'last_evaluated_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE operations.http_red_minute_buckets
            ADD CONSTRAINT operations_http_status_class_check
            CHECK (status_class IN ('1xx', '2xx', '3xx', '4xx', '5xx'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE operations.alert_states
            ADD CONSTRAINT operations_alert_state_check
            CHECK (state IN ('Healthy', 'Firing'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE operations.alert_states
            ADD CONSTRAINT operations_alert_notification_state_check
            CHECK (last_notification_state IS NULL OR last_notification_state IN ('Firing', 'Resolved'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operations.alert_states');
        Schema::dropIfExists('operations.http_red_minute_buckets');
    }
};
