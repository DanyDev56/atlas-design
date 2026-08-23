<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingWebhookVerifier;
use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

final readonly class StripeRecurringBillingWebhookVerifier implements RecurringBillingWebhookVerifier
{
    /** @param array<string, string> $priceIds */
    public function __construct(
        private StripeBillingClient $client,
        private string $webhookSecret,
        private array $priceIds,
    ) {}

    public function verify(string $provider, string $payload, string $signature): VerifiedRecurringBillingEvent
    {
        if ($provider !== 'stripe') {
            throw new \DomainException('Webhook provider unavailable.');
        }
        if (! str_starts_with($this->webhookSecret, 'whsec_')) {
            throw new \DomainException('Webhook verifier unavailable.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            return $this->normalize($event->toArray());
        } catch (SignatureVerificationException) {
            throw new \DomainException('Invalid webhook signature.');
        } catch (\UnexpectedValueException) {
            throw new \DomainException('Malformed webhook payload.');
        }
    }

    public function decodeTrusted(string $provider, string $payload): VerifiedRecurringBillingEvent
    {
        if ($provider !== 'stripe') {
            throw new \DomainException('Webhook provider unavailable.');
        }

        try {
            $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);

            return $this->normalize($decoded);
        } catch (\DomainException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new \DomainException('Malformed webhook payload.');
        }
    }

    /** @param array<string, mixed> $event */
    private function normalize(array $event): VerifiedRecurringBillingEvent
    {
        $providerEventId = trim((string) ($event['id'] ?? ''));
        $eventType = trim((string) ($event['type'] ?? ''));
        $occurredAt = $this->dateFromTimestamp($event['created'] ?? null);
        $object = $event['data']['object'] ?? null;
        if ($providerEventId === '' || ! is_array($object)) {
            throw new \DomainException('Malformed webhook payload.');
        }

        if (($object['object'] ?? null) === 'subscription') {
            $subscription = $object;
        } elseif (($object['object'] ?? null) === 'invoice') {
            $subscriptionId = $object['parent']['subscription_details']['subscription'] ?? null;
            if (is_array($subscriptionId)) {
                $subscriptionId = $subscriptionId['id'] ?? null;
            }
            if (! is_string($subscriptionId) || ! str_starts_with($subscriptionId, 'sub_')) {
                throw new \DomainException('Stripe invoice subscription unavailable.');
            }
            $subscription = $this->client->retrieveSubscription($subscriptionId);
        } else {
            throw new \DomainException('Stripe webhook event unsupported.');
        }

        $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
        $workspaceId = trim((string) ($metadata['workspace_id'] ?? ''));
        $planPriceId = trim((string) ($metadata['plan_price_id'] ?? ''));
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));
        $item = $subscription['items']['data'][0] ?? null;
        if (! is_array($item)) {
            throw new \DomainException('Stripe subscription item unavailable.');
        }

        $stripePrice = $item['price'] ?? null;
        $stripePriceId = is_array($stripePrice) ? ($stripePrice['id'] ?? null) : $stripePrice;
        if (
            $workspaceId === ''
            || $planPriceId === ''
            || ! str_starts_with($subscriptionId, 'sub_')
            || ! is_string($stripePriceId)
            || ! hash_equals((string) ($this->priceIds[$planPriceId] ?? ''), $stripePriceId)
        ) {
            throw new \DomainException('Stripe subscription metadata mismatch.');
        }

        return new VerifiedRecurringBillingEvent(
            provider: 'stripe',
            providerEventId: $providerEventId,
            type: $this->normalizedType($eventType, $object, $subscription),
            workspaceId: $workspaceId,
            providerSubscriptionReference: $subscriptionId,
            planPriceId: $planPriceId,
            occurredAt: $occurredAt,
            currentPeriodStart: $this->dateFromTimestamp($item['current_period_start'] ?? null),
            currentPeriodEnd: $this->dateFromTimestamp($item['current_period_end'] ?? null),
            cancelAtPeriodEnd: (bool) ($subscription['cancel_at_period_end'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $subscription
     */
    private function normalizedType(string $eventType, array $object, array $subscription): RecurringBillingEventType
    {
        if ($eventType === 'invoice.paid') {
            return ($object['billing_reason'] ?? null) === 'subscription_create'
                ? RecurringBillingEventType::Activated
                : RecurringBillingEventType::Renewed;
        }
        if ($eventType === 'invoice.payment_failed') {
            return RecurringBillingEventType::PaymentFailed;
        }
        if ($eventType === 'customer.subscription.deleted') {
            return RecurringBillingEventType::Canceled;
        }
        if (! in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated'], true)) {
            throw new \DomainException('Stripe webhook event unsupported.');
        }

        return match ((string) ($subscription['status'] ?? '')) {
            'active', 'trialing' => $eventType === 'customer.subscription.created'
                ? RecurringBillingEventType::Activated
                : RecurringBillingEventType::Updated,
            'past_due', 'unpaid', 'incomplete' => RecurringBillingEventType::PaymentFailed,
            'canceled', 'incomplete_expired', 'paused' => RecurringBillingEventType::Canceled,
            default => throw new \DomainException('Stripe subscription status unsupported.'),
        };
    }

    private function dateFromTimestamp(mixed $timestamp): \DateTimeImmutable
    {
        if (! is_int($timestamp) && ! (is_string($timestamp) && ctype_digit($timestamp))) {
            throw new \DomainException('Malformed webhook payload.');
        }

        return (new \DateTimeImmutable('@'.(string) $timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }
}
