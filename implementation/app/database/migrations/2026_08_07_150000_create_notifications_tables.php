<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS notifications');

        if (! $this->tableExists('notifications', 'notifications')) {
            Schema::create('notifications.notifications', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id');
                $table->uuid('recipient_user_id');
                $table->uuid('recommendation_id')->nullable();
                $table->string('notification_topic', 64);
                $table->string('status', 32);
                $table->string('read_state', 16);
                $table->string('priority', 16)->nullable();
                $table->jsonb('content');
                $table->jsonb('selected_channels');
                $table->unsignedInteger('revision');
                $table->timestampTz('created_at');
                $table->timestampTz('display_until')->nullable();
                $table->index(['workspace_id', 'recipient_user_id', 'status']);
            });
        }

        if (! $this->tableExists('notifications', 'preferences')) {
            Schema::create('notifications.preferences', function ($table): void {
                $table->uuid('workspace_id');
                $table->uuid('user_id');
                $table->string('in_app_mode', 16);
                $table->string('email_mode', 16);
                $table->unsignedInteger('revision');
                $table->timestampTz('updated_at');
                $table->primary(['workspace_id', 'user_id']);
            });
        }

        if (! $this->tableExists('notifications', 'topic_cursors')) {
            Schema::create('notifications.topic_cursors', function ($table): void {
                $table->uuid('workspace_id');
                $table->string('notification_topic', 64);
                $table->unsignedInteger('last_advisor_overview_version');
                $table->primary(['workspace_id', 'notification_topic']);
            });
        }

        if (! $this->tableExists('notifications', 'idempotency_keys')) {
            Schema::create('notifications.idempotency_keys', function ($table): void {
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
        Schema::dropIfExists('notifications.idempotency_keys');
        Schema::dropIfExists('notifications.topic_cursors');
        Schema::dropIfExists('notifications.preferences');
        Schema::dropIfExists('notifications.notifications');
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
