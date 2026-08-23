<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\StripeRecurringBillingGateway;
use PHPUnit\Framework\TestCase;

final class StripeRecurringBillingGatewayTest extends TestCase
{
    public function test_checkout_uses_the_external_price_and_preserves_atlas_metadata(): void
    {
        $client = new RecordingStripeBillingClient;
        $gateway = new StripeRecurringBillingGateway($client, ['atlas-price' => 'price_monthly']);

        $session = $gateway->createCheckoutSession(
            workspaceId: 'workspace-1',
            planPriceId: 'atlas-price',
            idempotencyKey: 'request-1',
            successUrl: 'https://atlas.test/app/settings/subscription',
            cancelUrl: 'https://atlas.test/app/settings/subscription',
        );

        self::assertSame('stripe', $session->provider);
        self::assertSame('price_monthly', $client->checkoutParameters['line_items'][0]['price']);
        self::assertSame('workspace-1', $client->checkoutParameters['client_reference_id']);
        self::assertSame('workspace-1', $client->checkoutParameters['subscription_data']['metadata']['workspace_id']);
        self::assertSame('atlas-price', $client->checkoutParameters['subscription_data']['metadata']['plan_price_id']);
        self::assertStringContainsString('checkout=pending', $client->checkoutParameters['success_url']);
        self::assertStringContainsString('{CHECKOUT_SESSION_ID}', $client->checkoutParameters['success_url']);
        self::assertStringStartsWith('atlas_checkout_', $client->checkoutIdempotencyKey);
    }

    public function test_portal_uses_the_customer_from_the_workspace_subscription(): void
    {
        $client = new RecordingStripeBillingClient;
        $gateway = new StripeRecurringBillingGateway($client, ['atlas-price' => 'price_monthly']);

        $session = $gateway->createPortalSession(
            workspaceId: 'workspace-1',
            providerSubscriptionReference: 'sub_atlas',
            returnUrl: 'https://atlas.test/app/settings/subscription',
        );

        self::assertSame('stripe', $session->provider);
        self::assertSame('cus_atlas', $client->portalParameters['customer']);
        self::assertSame('https://atlas.test/app/settings/subscription', $client->portalParameters['return_url']);
    }

    public function test_portal_rejects_a_subscription_owned_by_another_workspace(): void
    {
        $client = new RecordingStripeBillingClient;
        $client->subscription['metadata']['workspace_id'] = 'workspace-2';
        $gateway = new StripeRecurringBillingGateway($client, ['atlas-price' => 'price_monthly']);

        $this->expectExceptionMessage('Stripe subscription workspace mismatch.');
        $gateway->createPortalSession('workspace-1', 'sub_atlas', 'https://atlas.test/return');
    }
}

final class RecordingStripeBillingClient implements StripeBillingClient
{
    /** @var array<string, mixed> */
    public array $checkoutParameters = [];

    public string $checkoutIdempotencyKey = '';

    /** @var array<string, mixed> */
    public array $portalParameters = [];

    /** @var array<string, mixed> */
    public array $subscription = [
        'id' => 'sub_atlas',
        'customer' => 'cus_atlas',
        'metadata' => ['workspace_id' => 'workspace-1'],
    ];

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        $this->checkoutParameters = $parameters;
        $this->checkoutIdempotencyKey = $idempotencyKey;

        return ['id' => 'cs_test_atlas', 'url' => 'https://checkout.stripe.test/session'];
    }

    public function createPortalSession(array $parameters): array
    {
        $this->portalParameters = $parameters;

        return ['id' => 'bps_atlas', 'url' => 'https://billing.stripe.test/session'];
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->subscription;
    }
}
