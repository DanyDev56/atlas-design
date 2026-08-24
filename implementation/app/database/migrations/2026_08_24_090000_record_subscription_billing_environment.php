<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions.recurring_subscriptions', function ($table): void {
            $table->string('billing_environment', 16)->default('Unknown')->after('provider');
            $table->index(['billing_environment', 'status'], 'subscription_environment_status_idx');
        });

        Schema::table('subscriptions.webhook_inbox', function ($table): void {
            $table->string('billing_environment', 16)->default('Unknown')->after('provider');
            $table->index(['billing_environment', 'status'], 'webhook_environment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions.webhook_inbox', function ($table): void {
            $table->dropIndex('webhook_environment_status_idx');
            $table->dropColumn('billing_environment');
        });

        Schema::table('subscriptions.recurring_subscriptions', function ($table): void {
            $table->dropIndex('subscription_environment_status_idx');
            $table->dropColumn('billing_environment');
        });
    }
};
