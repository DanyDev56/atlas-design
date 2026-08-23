<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class VerifiedRecurringBillingEvent
{
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public RecurringBillingEventType $type,
        public string $workspaceId,
        public string $providerSubscriptionReference,
        public string $planPriceId,
        public \DateTimeImmutable $occurredAt,
        public \DateTimeImmutable $currentPeriodStart,
        public \DateTimeImmutable $currentPeriodEnd,
        public bool $cancelAtPeriodEnd,
    ) {
        if (
            trim($provider) === ''
            || trim($providerEventId) === ''
            || trim($workspaceId) === ''
            || trim($providerSubscriptionReference) === ''
            || trim($planPriceId) === ''
            || $currentPeriodEnd <= $currentPeriodStart
        ) {
            throw new \InvalidArgumentException('Invalid recurring billing event.');
        }
    }
}
