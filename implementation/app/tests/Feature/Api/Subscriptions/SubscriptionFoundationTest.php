<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Subscriptions;

use Atlas\Modules\Subscriptions\Application\StartTrialForWorkspaceHandler;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class SubscriptionFoundationTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_workspace_activation_starts_one_trial_and_exposes_candidate_catalog(): void
    {
        $owner = $this->onboardOwner($this, 'subscription-owner@test.local');

        app(OutboxProcessor::class)->processPending();

        $response = $this->getJson("/api/workspaces/{$owner['workspace_id']}/subscription", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('catalog.status', 'Candidate')
            ->assertJsonPath('catalog.public', false)
            ->assertJsonPath('catalog.plan.code', 'atlas_solo')
            ->assertJsonPath('catalog.plan.limits.members_total', 3)
            ->assertJsonCount(7, 'catalog.plan.capabilities')
            ->assertJsonPath('catalog.plan.prices.0.amount_minor', 2400)
            ->assertJsonPath('catalog.plan.prices.1.amount_minor', 24000)
            ->assertJsonPath('trial.status', 'Active')
            ->assertJsonPath('trial.remaining_days', 30)
            ->assertJsonPath('access.level', 'Full')
            ->assertJsonPath('access.source', 'Trial')
            ->assertJsonPath('access.limits.members_total', 3)
            ->assertJsonPath('commercialization.enforcement_enabled', false)
            ->assertJsonPath('commercialization.checkout_enabled', false);

        self::assertNotNull($response->json('trial.ends_at'));
        self::assertSame(1, DB::table('subscriptions.trials')->where('workspace_id', $owner['workspace_id'])->count());
        self::assertSame(1, DB::table('subscriptions.entitlements')->where('workspace_id', $owner['workspace_id'])->count());
        self::assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'subscriptions.trial_started')->count());
    }

    public function test_starting_trial_twice_for_workspace_is_idempotent(): void
    {
        $owner = $this->onboardOwner($this, 'subscription-idempotent@test.local');
        $startedAt = new \DateTimeImmutable('2026-08-23T10:00:00+00:00');
        $handler = app(StartTrialForWorkspaceHandler::class);

        $first = $handler->handle($owner['workspace_id'], $startedAt);
        $second = $handler->handle($owner['workspace_id'], $startedAt->modify('+1 day'));

        self::assertSame($first->id()->value, $second->id()->value);
        self::assertSame($first->endsAt()->format(DATE_ATOM), $second->endsAt()->format(DATE_ATOM));
        self::assertSame(1, DB::table('subscriptions.trials')->where('workspace_id', $owner['workspace_id'])->count());
        self::assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'subscriptions.trial_started')->count());
    }

    public function test_existing_active_workspaces_can_receive_a_fresh_trial_through_the_backfill_command(): void
    {
        $owner = $this->onboardOwner($this, 'subscription-backfill@test.local');

        $this->artisan('atlas:subscriptions:backfill-trials', ['--dry-run' => true])
            ->expectsOutput('1 active workspace(s) eligible for a fresh trial.')
            ->assertSuccessful();
        self::assertSame(0, DB::table('subscriptions.trials')->count());

        $this->artisan('atlas:subscriptions:backfill-trials')
            ->expectsOutput('Started 1 workspace trial(s).')
            ->assertSuccessful();
        $this->artisan('atlas:subscriptions:backfill-trials')
            ->expectsOutput('Started 0 workspace trial(s).')
            ->assertSuccessful();

        self::assertSame(1, DB::table('subscriptions.trials')->where('workspace_id', $owner['workspace_id'])->count());
        self::assertSame(1, DB::table('subscriptions.entitlements')->where('workspace_id', $owner['workspace_id'])->count());
        self::assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'subscriptions.trial_started')->count());
    }

    public function test_subscription_overview_is_isolated_by_workspace_membership(): void
    {
        $first = $this->onboardOwner($this, 'subscription-first@test.local');
        $second = $this->onboardOwner($this, 'subscription-second@test.local');

        $this->getJson("/api/workspaces/{$second['workspace_id']}/subscription", [
            'Authorization' => 'Bearer '.$first['token'],
        ])->assertForbidden();
    }

    public function test_checkout_is_disabled_until_commercial_gate_is_open(): void
    {
        $owner = $this->onboardOwner($this, 'subscription-disabled@test.local');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Monthly',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertConflict()
            ->assertJsonPath('messages.0', 'Checkout unavailable.');
    }

    public function test_fake_checkout_is_deterministic_when_explicitly_enabled(): void
    {
        config()->set('subscriptions.checkout_enabled', true);
        $owner = $this->onboardOwner($this, 'subscription-preview@test.local');
        $idempotencyKey = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $idempotencyKey,
        ];

        $first = $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Annual',
        ], $headers)->assertCreated()
            ->assertJsonPath('provider', 'fake')
            ->assertJsonPath('mode', 'Preview')
            ->json();

        $second = $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Annual',
        ], $headers)->assertCreated()->json();

        self::assertSame($first['checkout_session_id'], $second['checkout_session_id']);
        self::assertSame($first['checkout_url'], $second['checkout_url']);
        self::assertSame($first['provider'], $second['provider']);
        self::assertSame($first['mode'], $second['mode']);
        self::assertSame(1, DB::table('subscriptions.idempotency_keys')->count());
        self::assertSame(0, DB::table('subscriptions.trials')->where('workspace_id', $owner['workspace_id'])->count());
    }

    public function test_checkout_rejects_reuse_of_key_for_another_price(): void
    {
        config()->set('subscriptions.checkout_enabled', true);
        $owner = $this->onboardOwner($this, 'subscription-conflict@test.local');
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Monthly',
        ], $headers)->assertCreated();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Annual',
        ], $headers)->assertConflict()
            ->assertJsonPath('messages.0', 'Idempotency conflict.');
    }
}
