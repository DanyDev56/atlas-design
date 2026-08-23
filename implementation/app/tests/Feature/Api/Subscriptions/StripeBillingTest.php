<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Subscriptions;

use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class StripeBillingTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    private const string INTERNAL_MONTHLY_PRICE = 'a5d9e095-bcee-54ef-8b40-f47256997d40';

    private const string STRIPE_MONTHLY_PRICE = 'price_atlas_monthly';

    private const string WEBHOOK_SECRET = 'whsec_atlas_feature_secret';

    private FeatureStripeBillingClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('subscriptions.gateway', 'stripe');
        config()->set('subscriptions.checkout_enabled', true);
        config()->set('subscriptions.webhooks_enabled', true);
        config()->set('subscriptions.stripe.webhook_secret', self::WEBHOOK_SECRET);
        config()->set('subscriptions.stripe.price_ids', [
            self::INTERNAL_MONTHLY_PRICE => self::STRIPE_MONTHLY_PRICE,
        ]);
        $this->stripe = new FeatureStripeBillingClient;
        $this->app->instance(StripeBillingClient::class, $this->stripe);
    }

    public function test_checkout_redirect_is_returned_without_activating_the_subscription(): void
    {
        $owner = $this->onboardOwner($this, 'stripe-checkout@test.local');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/checkout", [
            'billing_interval' => 'Monthly',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('provider', 'stripe')
            ->assertJsonPath('mode', 'Payment')
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/atlas');

        self::assertSame($owner['workspace_id'], $this->stripe->checkoutParameters['subscription_data']['metadata']['workspace_id']);
        self::assertDatabaseMissing('subscriptions.recurring_subscriptions', [
            'workspace_id' => $owner['workspace_id'],
        ]);
    }

    public function test_signed_activation_enables_the_subscription_and_opens_its_portal(): void
    {
        $owner = $this->onboardOwner($this, 'stripe-portal@test.local');
        $this->stripe->subscription = $this->subscription($owner['workspace_id']);
        $payload = json_encode([
            'id' => 'evt_stripe_activation',
            'object' => 'event',
            'type' => 'customer.subscription.created',
            'created' => 1_787_479_200,
            'data' => ['object' => $this->stripe->subscription],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/subscriptions/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->signature($payload),
        ], $payload)->assertAccepted()
            ->assertJsonPath('status', 'Processed');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/subscription/portal", [], [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertCreated()
            ->assertJsonPath('provider', 'stripe')
            ->assertJsonPath('portal_url', 'https://billing.stripe.test/atlas');

        self::assertSame('cus_atlas', $this->stripe->portalParameters['customer']);
    }

    /** @return array<string, mixed> */
    private function subscription(string $workspaceId): array
    {
        return [
            'id' => 'sub_atlas',
            'object' => 'subscription',
            'status' => 'active',
            'customer' => 'cus_atlas',
            'cancel_at_period_end' => false,
            'metadata' => [
                'workspace_id' => $workspaceId,
                'plan_price_id' => self::INTERNAL_MONTHLY_PRICE,
            ],
            'items' => ['data' => [[
                'id' => 'si_atlas',
                'current_period_start' => 1_787_479_200,
                'current_period_end' => 1_790_157_600,
                'price' => ['id' => self::STRIPE_MONTHLY_PRICE],
            ]]],
        ];
    }

    private function signature(string $payload): string
    {
        $timestamp = time();

        return sprintf(
            't=%d,v1=%s',
            $timestamp,
            hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET),
        );
    }
}

final class FeatureStripeBillingClient implements StripeBillingClient
{
    /** @var array<string, mixed> */
    public array $checkoutParameters = [];

    /** @var array<string, mixed> */
    public array $portalParameters = [];

    /** @var array<string, mixed> */
    public array $subscription = [];

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        $this->checkoutParameters = $parameters;

        return ['id' => 'cs_atlas', 'url' => 'https://checkout.stripe.test/atlas'];
    }

    public function createPortalSession(array $parameters): array
    {
        $this->portalParameters = $parameters;

        return ['id' => 'bps_atlas', 'url' => 'https://billing.stripe.test/atlas'];
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->subscription;
    }
}
