<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\StripeRecurringBillingWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class StripeRecurringBillingWebhookVerifierTest extends TestCase
{
    private const string SECRET = 'whsec_atlas_test_secret';

    public function test_signed_active_subscription_is_normalized_as_activation(): void
    {
        $payload = json_encode($this->event('customer.subscription.created'), JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = sprintf(
            't=%d,v1=%s',
            $timestamp,
            hash_hmac('sha256', $timestamp.'.'.$payload, self::SECRET),
        );

        $event = $this->verifier()->verify('stripe', $payload, $signature);

        self::assertSame(RecurringBillingEventType::Activated, $event->type);
        self::assertSame('workspace-1', $event->workspaceId);
        self::assertSame('sub_atlas', $event->providerSubscriptionReference);
        self::assertSame('atlas-price', $event->planPriceId);
        self::assertSame('2026-08-23T10:00:00+00:00', $event->currentPeriodStart->format(DATE_ATOM));
    }

    public function test_invoice_failure_retrieves_the_subscription_and_is_normalized(): void
    {
        $event = $this->event('invoice.payment_failed');
        $event['data']['object'] = [
            'id' => 'in_atlas',
            'object' => 'invoice',
            'parent' => [
                'subscription_details' => ['subscription' => 'sub_atlas'],
            ],
        ];

        $normalized = $this->verifier()->decodeTrusted(
            'stripe',
            json_encode($event, JSON_THROW_ON_ERROR),
        );

        self::assertSame(RecurringBillingEventType::PaymentFailed, $normalized->type);
        self::assertSame('sub_atlas', $normalized->providerSubscriptionReference);
    }

    public function test_active_subscription_update_is_not_treated_as_a_paid_renewal(): void
    {
        $normalized = $this->verifier()->decodeTrusted(
            'stripe',
            json_encode($this->event('customer.subscription.updated'), JSON_THROW_ON_ERROR),
        );

        self::assertSame(RecurringBillingEventType::Updated, $normalized->type);
    }

    public function test_subscription_price_must_match_the_configured_stripe_price(): void
    {
        $event = $this->event('customer.subscription.updated');
        $event['data']['object']['items']['data'][0]['price']['id'] = 'price_other';

        $this->expectExceptionMessage('Stripe subscription metadata mismatch.');
        $this->verifier()->decodeTrusted('stripe', json_encode($event, JSON_THROW_ON_ERROR));
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = json_encode($this->event('customer.subscription.created'), JSON_THROW_ON_ERROR);

        $this->expectExceptionMessage('Invalid webhook signature.');
        $this->verifier()->verify('stripe', $payload, 't=1,v1=invalid');
    }

    private function verifier(): StripeRecurringBillingWebhookVerifier
    {
        return new StripeRecurringBillingWebhookVerifier(
            new StripeWebhookClient($this->subscription()),
            self::SECRET,
            ['atlas-price' => 'price_monthly'],
        );
    }

    /** @return array<string, mixed> */
    private function event(string $type): array
    {
        return [
            'id' => 'evt_atlas_'.str_replace('.', '_', $type),
            'object' => 'event',
            'type' => $type,
            'created' => 1_787_479_200,
            'data' => ['object' => $this->subscription()],
        ];
    }

    /** @return array<string, mixed> */
    private function subscription(): array
    {
        return [
            'id' => 'sub_atlas',
            'object' => 'subscription',
            'status' => 'active',
            'customer' => 'cus_atlas',
            'cancel_at_period_end' => false,
            'metadata' => [
                'workspace_id' => 'workspace-1',
                'plan_price_id' => 'atlas-price',
            ],
            'items' => [
                'data' => [[
                    'id' => 'si_atlas',
                    'current_period_start' => 1_787_479_200,
                    'current_period_end' => 1_790_157_600,
                    'price' => ['id' => 'price_monthly'],
                ]],
            ],
        ];
    }
}

final readonly class StripeWebhookClient implements StripeBillingClient
{
    /** @param array<string, mixed> $subscription */
    public function __construct(private array $subscription) {}

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        throw new \LogicException('Not used.');
    }

    public function createPortalSession(array $parameters): array
    {
        throw new \LogicException('Not used.');
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->subscription;
    }
}
