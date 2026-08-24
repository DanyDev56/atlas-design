<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionReconciliationException;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\StripeRecurringBillingReconciliationGateway;
use PHPUnit\Framework\TestCase;

final class StripeRecurringBillingReconciliationGatewayTest extends TestCase
{
    public function test_it_normalizes_a_matching_stripe_subscription(): void
    {
        $client = new ReconciliationStripeClient;
        $gateway = new StripeRecurringBillingReconciliationGateway($client, ['price-1' => 'price_stripe']);

        $state = $gateway->inspect($this->subscription());

        self::assertSame(Subscription::STATUS_PAST_DUE, $state->status);
        self::assertSame('2026-08-23T00:00:00+00:00', $state->currentPeriodStart->format(DATE_ATOM));
        self::assertSame('2026-09-23T00:00:00+00:00', $state->currentPeriodEnd->format(DATE_ATOM));
        self::assertTrue($state->cancelAtPeriodEnd);
    }

    public function test_it_rejects_metadata_or_price_mismatch(): void
    {
        $client = new ReconciliationStripeClient;
        $client->subscription['metadata']['workspace_id'] = 'another-workspace';
        $gateway = new StripeRecurringBillingReconciliationGateway($client, ['price-1' => 'price_stripe']);

        try {
            $gateway->inspect($this->subscription());
            self::fail('Expected mismatch.');
        } catch (SubscriptionReconciliationException $exception) {
            self::assertSame('SubscriptionProviderStateMismatch', $exception->errorCode);
            self::assertSame(409, $exception->httpStatus);
        }
    }

    private function subscription(): Subscription
    {
        return Subscription::reconstitute([
            'id' => 'subscription-1', 'workspace_id' => 'workspace-1', 'plan_id' => 'plan-1', 'plan_price_id' => 'price-1',
            'provider' => 'stripe', 'provider_subscription_reference' => 'sub_atlas', 'status' => 'Active',
            'current_period_start' => '2026-07-23T00:00:00+00:00', 'current_period_end' => '2026-08-23T00:00:00+00:00',
            'cancel_at_period_end' => false, 'canceled_at' => null, 'past_due_since' => null,
            'last_provider_event_at' => '2026-08-23T10:00:00+00:00', 'version' => 1,
        ]);
    }
}

final class ReconciliationStripeClient implements StripeBillingClient
{
    /** @var array<string, mixed> */
    public array $subscription = [
        'id' => 'sub_atlas', 'status' => 'past_due', 'cancel_at_period_end' => true, 'canceled_at' => null,
        'metadata' => ['workspace_id' => 'workspace-1', 'plan_price_id' => 'price-1'],
        'items' => ['data' => [[
            'price' => ['id' => 'price_stripe'],
            'current_period_start' => 1787443200,
            'current_period_end' => 1790121600,
        ]]],
    ];

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array { return []; }
    public function createPortalSession(array $parameters): array { return []; }
    public function retrieveSubscription(string $subscriptionId): array { return $this->subscription; }
}
