<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string ATLAS_SOLO_PLAN_ID = 'c7ab1c54-33cf-5c00-9de2-5ae6d2c7da18';

    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS subscriptions');

        if (! $this->tableExists('subscriptions', 'plans')) {
            Schema::create('subscriptions.plans', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('code', 64);
                $table->unsignedSmallInteger('version');
                $table->string('display_name', 120);
                $table->string('status', 32);
                $table->boolean('is_public')->default(false);
                $table->jsonb('entitlements');
                $table->timestampTz('created_at');
                $table->unique(['code', 'version']);
            });
        }

        if (! $this->tableExists('subscriptions', 'plan_prices')) {
            Schema::create('subscriptions.plan_prices', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('plan_id');
                $table->string('billing_interval', 16);
                $table->char('currency', 3);
                $table->unsignedInteger('amount_minor');
                $table->string('status', 32);
                $table->timestampTz('effective_from');
                $table->timestampTz('effective_until')->nullable();
                $table->string('provider_price_reference')->nullable();
                $table->timestampTz('created_at');
                $table->foreign('plan_id')->references('id')->on('subscriptions.plans');
                $table->unique(['plan_id', 'billing_interval', 'currency']);
            });
        }

        if (! $this->tableExists('subscriptions', 'trials')) {
            Schema::create('subscriptions.trials', function ($table): void {
                $table->uuid('id')->primary();
                $table->uuid('workspace_id')->unique();
                $table->uuid('plan_id');
                $table->string('status', 32);
                $table->timestampTz('started_at');
                $table->timestampTz('ends_at');
                $table->unsignedInteger('version');
                $table->timestampTz('created_at');
                $table->timestampTz('updated_at');
                $table->foreign('plan_id')->references('id')->on('subscriptions.plans');
                $table->index(['status', 'ends_at']);
            });
        }

        if (! $this->tableExists('subscriptions', 'entitlements')) {
            Schema::create('subscriptions.entitlements', function ($table): void {
                $table->uuid('workspace_id')->primary();
                $table->string('source_type', 32);
                $table->uuid('source_id');
                $table->jsonb('full_capabilities');
                $table->jsonb('restricted_capabilities');
                $table->jsonb('limits');
                $table->timestampTz('valid_until')->nullable();
                $table->timestampTz('computed_at');
                $table->unsignedInteger('version');
                $table->timestampTz('updated_at');
                $table->index(['source_type', 'source_id']);
            });
        }

        if (! $this->tableExists('subscriptions', 'idempotency_keys')) {
            Schema::create('subscriptions.idempotency_keys', function ($table): void {
                $table->string('scope');
                $table->string('key');
                $table->string('fingerprint');
                $table->jsonb('response_payload');
                $table->timestampTz('created_at');
                $table->primary(['scope', 'key']);
            });
        }

        $now = '2026-08-23 00:00:00+00';
        DB::table('subscriptions.plans')->insertOrIgnore([
            'id' => self::ATLAS_SOLO_PLAN_ID,
            'code' => 'atlas_solo',
            'version' => 1,
            'display_name' => 'Atlas Solo',
            'status' => 'Candidate',
            'is_public' => false,
            'entitlements' => json_encode([
                'capabilities' => [
                    'workspace.read',
                    'workspace.mutate',
                    'documents.send',
                    'analytics.evaluate',
                    'members.invite',
                    'data.export',
                    'subscription.manage',
                ],
                'limits' => [
                    'owners' => 1,
                    'members' => 2,
                    'members_total' => 3,
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        DB::table('subscriptions.plan_prices')->insertOrIgnore([
            [
                'id' => 'a5d9e095-bcee-54ef-8b40-f47256997d40',
                'plan_id' => self::ATLAS_SOLO_PLAN_ID,
                'billing_interval' => 'Monthly',
                'currency' => 'EUR',
                'amount_minor' => 2400,
                'status' => 'Candidate',
                'effective_from' => $now,
                'effective_until' => null,
                'provider_price_reference' => null,
                'created_at' => $now,
            ],
            [
                'id' => 'b7f839e4-f01b-5fd8-866d-6e55b2bb32e6',
                'plan_id' => self::ATLAS_SOLO_PLAN_ID,
                'billing_interval' => 'Annual',
                'currency' => 'EUR',
                'amount_minor' => 24000,
                'status' => 'Candidate',
                'effective_from' => $now,
                'effective_until' => null,
                'provider_price_reference' => null,
                'created_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions.idempotency_keys');
        Schema::dropIfExists('subscriptions.entitlements');
        Schema::dropIfExists('subscriptions.trials');
        Schema::dropIfExists('subscriptions.plan_prices');
        Schema::dropIfExists('subscriptions.plans');
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
