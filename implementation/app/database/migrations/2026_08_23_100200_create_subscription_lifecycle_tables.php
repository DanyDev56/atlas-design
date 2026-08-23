<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists('subscriptions', 'recurring_subscriptions')) {
            Schema::create('subscriptions.recurring_subscriptions', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id')->unique();
                $table->uuid('plan_id');
                $table->uuid('plan_price_id');
                $table->string('provider', 32);
                $table->string('provider_subscription_reference', 160);
                $table->string('status', 32);
                $table->timestampTz('current_period_start');
                $table->timestampTz('current_period_end');
                $table->boolean('cancel_at_period_end')->default(false);
                $table->timestampTz('canceled_at')->nullable();
                $table->timestampTz('last_provider_event_at');
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->foreign('plan_id')->references('id')->on('subscriptions.plans');
                $table->foreign('plan_price_id')->references('id')->on('subscriptions.plan_prices');
                $table->unique(['provider', 'provider_subscription_reference']);
                $table->index(['status', 'current_period_end']);
            });
        }

        if (! $this->tableExists('subscriptions', 'webhook_inbox')) {
            Schema::create('subscriptions.webhook_inbox', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('provider', 32);
                $table->string('provider_event_id', 160);
                $table->string('event_type', 80);
                $table->string('provider_subscription_reference', 160);
                $table->string('payload_hash', 64);
                $table->jsonb('payload');
                $table->timestampTz('occurred_at');
                $table->string('status', 32);
                $table->unsignedInteger('attempts')->default(0);
                $table->string('failure_reason')->nullable();
                $table->timestampTz('received_at');
                $table->timestampTz('processed_at')->nullable();
                $table->unique(['provider', 'provider_event_id']);
                $table->index(['status', 'received_at']);
                $table->index(['provider', 'provider_subscription_reference', 'occurred_at'], 'subscription_webhook_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions.webhook_inbox');
        Schema::dropIfExists('subscriptions.recurring_subscriptions');
    }

    private function tableExists(string $schema, string $table): bool
    {
        return (bool) DB::selectOne(
            'SELECT to_regclass(?) IS NOT NULL AS exists',
            ["{$schema}.{$table}"],
        )->exists;
    }
};
