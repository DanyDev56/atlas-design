<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class SubscriptionEntitlementPolicy
{
    public function __construct(private ?int $pastDueGraceDays)
    {
        if ($pastDueGraceDays !== null && $pastDueGraceDays < 0) {
            throw new \InvalidArgumentException('Invalid past-due grace period.');
        }
    }

    public function validUntil(Subscription $subscription): \DateTimeImmutable
    {
        if ($subscription->status() !== Subscription::STATUS_PAST_DUE || $this->pastDueGraceDays === null) {
            return $subscription->currentPeriodEnd();
        }

        $graceEnd = $subscription->lastProviderEventAt()->modify(sprintf('+%d days', $this->pastDueGraceDays));

        return $graceEnd > $subscription->currentPeriodEnd()
            ? $graceEnd
            : $subscription->currentPeriodEnd();
    }
}
