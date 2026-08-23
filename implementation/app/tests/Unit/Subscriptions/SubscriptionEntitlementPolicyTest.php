<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionEntitlementPolicy;
use Atlas\Modules\Subscriptions\Domain\SubscriptionId;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;
use PHPUnit\Framework\TestCase;

final class SubscriptionEntitlementPolicyTest extends TestCase
{
    public function test_active_and_canceled_access_end_with_the_paid_period(): void
    {
        $subscription = Subscription::activate(
            new SubscriptionId('subscription-1'),
            'plan-1',
            $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'),
        );
        $policy = new SubscriptionEntitlementPolicy(7);

        self::assertSame('2026-09-23T00:00:00+00:00', $policy->validUntil($subscription)->format(DATE_ATOM));

        $subscription->apply($this->event(RecurringBillingEventType::Canceled, '2026-08-24T10:00:00+00:00'));
        self::assertSame('2026-09-23T00:00:00+00:00', $policy->validUntil($subscription)->format(DATE_ATOM));
    }

    public function test_past_due_access_uses_the_later_of_paid_period_and_configured_grace(): void
    {
        $subscription = Subscription::activate(
            new SubscriptionId('subscription-1'),
            'plan-1',
            $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'),
        );
        $policy = new SubscriptionEntitlementPolicy(7);

        $subscription->apply($this->event(RecurringBillingEventType::PaymentFailed, '2026-09-24T10:00:00+00:00'));

        self::assertSame('2026-10-01T10:00:00+00:00', $policy->validUntil($subscription)->format(DATE_ATOM));
    }

    public function test_repeated_failures_do_not_restart_the_grace_period(): void
    {
        $subscription = Subscription::activate(
            new SubscriptionId('subscription-1'),
            'plan-1',
            $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'),
        );
        $policy = new SubscriptionEntitlementPolicy(14);

        $subscription->apply($this->event(RecurringBillingEventType::PaymentFailed, '2026-09-24T10:00:00+00:00'));
        $subscription->apply($this->event(RecurringBillingEventType::PaymentFailed, '2026-09-30T10:00:00+00:00'));

        self::assertSame('2026-10-08T10:00:00+00:00', $policy->validUntil($subscription)->format(DATE_ATOM));
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
