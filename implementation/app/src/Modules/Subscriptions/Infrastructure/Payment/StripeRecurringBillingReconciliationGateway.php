<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingReconciliationGateway;
use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionReconciliationException;

final readonly class StripeRecurringBillingReconciliationGateway implements RecurringBillingReconciliationGateway
{
    /** @param array<string, string> $priceIds */
    public function __construct(
        private StripeBillingClient $client,
        private array $priceIds,
    ) {}

    public function inspect(Subscription $subscription): ProviderSubscriptionState
    {
        if ($subscription->provider() !== 'stripe' || ! str_starts_with($subscription->providerReference(), 'sub_')) {
            throw new SubscriptionReconciliationException('SubscriptionProviderMismatch', 'Cet abonnement ne peut pas être comparé avec Stripe.', 409);
        }

        try {
            $remote = $this->client->retrieveSubscription($subscription->providerReference());
        } catch (\DomainException $exception) {
            throw new SubscriptionReconciliationException('SubscriptionProviderUnavailable', 'Stripe est momentanément indisponible.', 503, $exception);
        }

        $metadata = is_array($remote['metadata'] ?? null) ? $remote['metadata'] : [];
        $items = is_array($remote['items']['data'] ?? null) ? $remote['items']['data'] : [];
        $item = is_array($items[0] ?? null) ? $items[0] : [];
        $stripePrice = is_array($item['price'] ?? null) ? ($item['price']['id'] ?? null) : null;
        $expectedStripePrice = $this->priceIds[$subscription->planPriceId()] ?? null;
        if (($remote['id'] ?? null) !== $subscription->providerReference()
            || ($metadata['workspace_id'] ?? null) !== $subscription->workspaceId()
            || ($metadata['plan_price_id'] ?? null) !== $subscription->planPriceId()
            || ! is_string($expectedStripePrice)
            || $stripePrice !== $expectedStripePrice) {
            throw new SubscriptionReconciliationException('SubscriptionProviderStateMismatch', 'L’identité ou le plan de l’abonnement Stripe ne correspond pas à Atlas.', 409);
        }

        $status = match ((string) ($remote['status'] ?? '')) {
            'active', 'trialing' => Subscription::STATUS_ACTIVE,
            'past_due', 'unpaid', 'incomplete' => Subscription::STATUS_PAST_DUE,
            'canceled', 'incomplete_expired', 'paused' => Subscription::STATUS_CANCELED,
            default => throw new SubscriptionReconciliationException('SubscriptionProviderStatusUnsupported', 'L’état Stripe reçu ne peut pas être réconcilié automatiquement.', 409),
        };
        $start = $item['current_period_start'] ?? $remote['current_period_start'] ?? null;
        $end = $item['current_period_end'] ?? $remote['current_period_end'] ?? null;
        if (! is_int($start) || ! is_int($end) || $end <= $start) {
            throw new SubscriptionReconciliationException('SubscriptionProviderResponseInvalid', 'La période renvoyée par Stripe est invalide.', 502);
        }
        $canceledAt = $remote['canceled_at'] ?? null;
        if ($status === Subscription::STATUS_CANCELED && ! is_int($canceledAt)) {
            throw new SubscriptionReconciliationException('SubscriptionProviderResponseInvalid', 'La date de résiliation renvoyée par Stripe est invalide.', 502);
        }

        return new ProviderSubscriptionState(
            provider: 'stripe',
            workspaceId: $subscription->workspaceId(),
            providerSubscriptionReference: $subscription->providerReference(),
            planPriceId: $subscription->planPriceId(),
            status: $status,
            currentPeriodStart: $this->date($start),
            currentPeriodEnd: $this->date($end),
            cancelAtPeriodEnd: (bool) ($remote['cancel_at_period_end'] ?? false),
            canceledAt: is_int($canceledAt) ? $this->date($canceledAt) : null,
            observedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    private function date(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone('UTC'));
    }
}
