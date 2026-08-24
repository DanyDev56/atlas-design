<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingReconciliationGateway;
use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
use Atlas\Modules\Subscriptions\Domain\Subscription;

final class FakeRecurringBillingReconciliationGateway implements RecurringBillingReconciliationGateway
{
    public function inspect(Subscription $subscription): ProviderSubscriptionState
    {
        return new ProviderSubscriptionState(
            provider: $subscription->provider(),
            workspaceId: $subscription->workspaceId(),
            providerSubscriptionReference: $subscription->providerReference(),
            planPriceId: $subscription->planPriceId(),
            status: $subscription->status(),
            currentPeriodStart: $subscription->currentPeriodStart(),
            currentPeriodEnd: $subscription->currentPeriodEnd(),
            cancelAtPeriodEnd: $subscription->cancelAtPeriodEnd(),
            canceledAt: $subscription->canceledAt(),
            observedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
