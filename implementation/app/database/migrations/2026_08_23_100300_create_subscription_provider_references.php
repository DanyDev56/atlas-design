<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tableExists('subscriptions', 'subscription_provider_references')) {
            return;
        }

        Schema::create('subscriptions.subscription_provider_references', function ($table): void {
            $table->uuid('subscription_id');
            $table->string('provider', 32);
            $table->string('provider_subscription_reference', 160);
            $table->timestampTz('created_at');
            $table->timestampTz('retired_at')->nullable();
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions.recurring_subscriptions')
                ->cascadeOnDelete();
            $table->unique(['provider', 'provider_subscription_reference'], 'subscription_provider_reference_unique');
            $table->index('subscription_id');
        });

        DB::table('subscriptions.recurring_subscriptions')
            ->orderBy('created_at')
            ->each(function (object $subscription): void {
                DB::table('subscriptions.subscription_provider_references')->insertOrIgnore([
                    'subscription_id' => $subscription->id,
                    'provider' => $subscription->provider,
                    'provider_subscription_reference' => $subscription->provider_subscription_reference,
                    'created_at' => $subscription->created_at,
                    'retired_at' => null,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions.subscription_provider_references');
    }

    private function tableExists(string $schema, string $table): bool
    {
        return (bool) DB::selectOne(
            'SELECT to_regclass(?) IS NOT NULL AS exists',
            ["{$schema}.{$table}"],
        )->exists;
    }
};
