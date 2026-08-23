<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Subscriptions;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class RecurringBillingWebhookTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    private const string SECRET = 'atlas-feature-webhook-secret';

    private const string ANNUAL_PRICE_ID = 'b7f839e4-f01b-5fd8-866d-6e55b2bb32e6';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('subscriptions.webhooks_enabled', true);
        config()->set('subscriptions.fake_webhook_secret', self::SECRET);
    }

    public function test_signed_activation_is_processed_once_and_exposed_in_overview(): void
    {
        $owner = $this->onboardOwner($this, 'webhook-owner@test.local');
        $payload = $this->payload('event-activation', 'subscription.activated', $owner['workspace_id']);

        $this->postWebhook($payload)->assertAccepted()
            ->assertJsonPath('status', 'Processed')
            ->assertJsonPath('duplicate', false);
        $this->postWebhook($payload)->assertOk()
            ->assertJsonPath('status', 'Processed')
            ->assertJsonPath('duplicate', true);

        self::assertSame(1, DB::table('subscriptions.webhook_inbox')->count());
        self::assertSame(1, DB::table('subscriptions.recurring_subscriptions')->count());
        self::assertSame('Subscription', DB::table('subscriptions.entitlements')->value('source_type'));

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/subscription", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('subscription.status', 'Active')
            ->assertJsonPath('subscription.provider', 'fake')
            ->assertJsonPath('access.source', 'Subscription');
    }

    public function test_invalid_signature_and_conflicting_event_id_are_rejected(): void
    {
        $owner = $this->onboardOwner($this, 'webhook-security@test.local');
        $payload = $this->payload('event-security', 'subscription.activated', $owner['workspace_id']);

        $this->postWebhook($payload, 'invalid')->assertUnauthorized();
        self::assertSame(0, DB::table('subscriptions.webhook_inbox')->count());

        $this->postWebhook($payload)->assertAccepted();
        $conflicting = $this->payload(
            'event-security',
            'subscription.activated',
            $owner['workspace_id'],
            subscriptionReference: 'fake-subscription-conflict',
        );
        $this->postWebhook($conflicting)->assertConflict()
            ->assertJsonPath('messages.0', 'Webhook event id conflict.');
        self::assertSame(1, DB::table('subscriptions.webhook_inbox')->count());
    }

    public function test_event_already_claimed_by_another_delivery_is_not_processed_twice(): void
    {
        $owner = $this->onboardOwner($this, 'webhook-concurrency@test.local');
        $payload = $this->payload('event-concurrency', 'subscription.activated', $owner['workspace_id']);

        $this->postWebhook($payload)->assertAccepted();
        DB::table('subscriptions.webhook_inbox')
            ->where('provider_event_id', 'event-concurrency')
            ->update(['status' => 'Processing']);

        $this->postWebhook($payload)->assertOk()
            ->assertJsonPath('status', 'Processing')
            ->assertJsonPath('duplicate', true);

        self::assertSame(1, DB::table('subscriptions.recurring_subscriptions')->count());
        self::assertSame(1, DB::table('subscriptions.webhook_inbox')->value('attempts'));
    }

    public function test_out_of_order_event_is_deferred_then_replayed_after_activation(): void
    {
        $owner = $this->onboardOwner($this, 'webhook-order@test.local');
        $renewal = $this->payload(
            'event-renewal',
            'subscription.renewed',
            $owner['workspace_id'],
            occurredAt: '2026-09-23T10:00:00+00:00',
            periodStart: '2026-09-23T00:00:00+00:00',
            periodEnd: '2026-10-23T00:00:00+00:00',
        );

        $this->postWebhook($renewal)->assertAccepted()->assertJsonPath('status', 'Deferred');
        $this->postWebhook($this->payload(
            'event-activation-order',
            'subscription.activated',
            $owner['workspace_id'],
        ))->assertAccepted()->assertJsonPath('status', 'Processed');

        $this->artisan('atlas:subscriptions:replay-webhook', [
            'provider' => 'fake',
            'event-id' => 'event-renewal',
        ])->expectsOutput('Webhook event-renewal is Processed.')->assertSuccessful();

        self::assertSame(
            '2026-10-23 00:00:00+00',
            DB::table('subscriptions.recurring_subscriptions')->value('current_period_end'),
        );
        self::assertSame('Processed', DB::table('subscriptions.webhook_inbox')->where('provider_event_id', 'event-renewal')->value('status'));

        $stale = $this->payload(
            'event-stale',
            'subscription.payment_failed',
            $owner['workspace_id'],
            occurredAt: '2026-09-01T10:00:00+00:00',
        );
        $this->postWebhook($stale)->assertAccepted()->assertJsonPath('status', 'Ignored');
        self::assertSame('Active', DB::table('subscriptions.recurring_subscriptions')->value('status'));
    }

    /** @param array<string, mixed> $payload */
    private function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/subscriptions/webhooks/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ATLAS_SIGNATURE' => $signature ?? hash_hmac('sha256', $raw, self::SECRET),
        ], $raw);
    }

    /** @return array<string, mixed> */
    private function payload(
        string $eventId,
        string $type,
        string $workspaceId,
        string $occurredAt = '2026-08-23T10:00:00+00:00',
        string $periodStart = '2026-08-23T00:00:00+00:00',
        string $periodEnd = '2026-09-23T00:00:00+00:00',
        string $subscriptionReference = 'fake-subscription-1',
    ): array {
        return [
            'id' => $eventId,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'data' => [
                'workspace_id' => $workspaceId,
                'subscription_reference' => $subscriptionReference,
                'plan_price_id' => self::ANNUAL_PRICE_ID,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
            ],
        ];
    }
}
