<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionId;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function test_lifecycle_follows_verified_events_and_rejects_stale_updates(): void
    {
        $activated = $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00');
        $subscription = Subscription::activate(new SubscriptionId('subscription-1'), 'plan-1', $activated);

        self::assertSame('Active', $subscription->status());
        self::assertTrue($subscription->apply($this->event(
            RecurringBillingEventType::PaymentFailed,
            '2026-08-24T10:00:00+00:00',
        )));
        self::assertSame('PastDue', $subscription->status());

        self::assertTrue($subscription->apply($this->event(
            RecurringBillingEventType::Renewed,
            '2026-08-25T10:00:00+00:00',
        )));
        self::assertSame('Active', $subscription->status());

        self::assertFalse($subscription->apply($this->event(
            RecurringBillingEventType::PaymentFailed,
            '2026-08-24T12:00:00+00:00',
        )));
        self::assertSame('Active', $subscription->status());

        self::assertTrue($subscription->apply($this->event(
            RecurringBillingEventType::Canceled,
            '2026-08-26T10:00:00+00:00',
        )));
        self::assertSame('Canceled', $subscription->status());
        self::assertTrue($subscription->cancelAtPeriodEnd());
        self::assertNotNull($subscription->canceledAt());
        self::assertSame(4, $subscription->version());
    }

    private function event(RecurringBillingEventType $type, string $occurredAt): VerifiedRecurringBillingEvent
    {
        return new VerifiedRecurringBillingEvent(
            provider: 'fake',
            providerEventId: 'event-'.$type->name.'-'.$occurredAt,
            type: $type,
            workspaceId: 'workspace-1',
            providerSubscriptionReference: 'fake-subscription-1',
            planPriceId: 'price-1',
            occurredAt: new \DateTimeImmutable($occurredAt),
            currentPeriodStart: new \DateTimeImmutable('2026-08-23T00:00:00+00:00'),
            currentPeriodEnd: new \DateTimeImmutable('2026-09-23T00:00:00+00:00'),
            cancelAtPeriodEnd: false,
        );
    }
}
