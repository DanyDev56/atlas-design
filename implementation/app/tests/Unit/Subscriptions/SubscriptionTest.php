<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
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

    public function test_canceled_subscription_can_be_restored_or_replaced(): void
    {
        $subscription = Subscription::activate(
            new SubscriptionId('subscription-1'),
            'plan-1',
            $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'),
        );
        $subscription->apply($this->event(RecurringBillingEventType::Canceled, '2026-08-24T10:00:00+00:00'));
        $subscription->apply($this->event(RecurringBillingEventType::Renewed, '2026-08-25T10:00:00+00:00'));

        self::assertSame('Active', $subscription->status());
        self::assertNull($subscription->canceledAt());

        $subscription->apply($this->event(RecurringBillingEventType::Canceled, '2026-08-26T10:00:00+00:00'));
        $subscription->resubscribe('plan-2', new VerifiedRecurringBillingEvent(
            provider: 'fake',
            providerEventId: 'event-replacement',
            type: RecurringBillingEventType::Activated,
            workspaceId: 'workspace-1',
            providerSubscriptionReference: 'fake-subscription-2',
            planPriceId: 'price-2',
            occurredAt: new \DateTimeImmutable('2026-08-27T10:00:00+00:00'),
            currentPeriodStart: new \DateTimeImmutable('2026-08-27T00:00:00+00:00'),
            currentPeriodEnd: new \DateTimeImmutable('2026-09-27T00:00:00+00:00'),
            cancelAtPeriodEnd: false,
        ));

        self::assertSame('Active', $subscription->status());
        self::assertSame('plan-2', $subscription->planId());
        self::assertSame('price-2', $subscription->planPriceId());
        self::assertSame('fake-subscription-2', $subscription->providerReference());
        self::assertSame(5, $subscription->version());
    }

    public function test_unpaid_period_updates_do_not_extend_the_last_paid_period_or_clear_past_due(): void
    {
        $subscription = Subscription::activate(
            new SubscriptionId('subscription-1'),
            'plan-1',
            $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'),
        );

        $subscription->apply($this->eventWithPeriod(
            RecurringBillingEventType::Updated,
            '2026-09-23T10:00:00+00:00',
            '2026-09-23T00:00:00+00:00',
            '2026-10-23T00:00:00+00:00',
        ));
        $subscription->apply($this->eventWithPeriod(
            RecurringBillingEventType::PaymentFailed,
            '2026-09-23T10:01:00+00:00',
            '2026-09-23T00:00:00+00:00',
            '2026-10-23T00:00:00+00:00',
        ));
        $subscription->apply($this->eventWithPeriod(
            RecurringBillingEventType::Updated,
            '2026-09-23T10:02:00+00:00',
            '2026-09-23T00:00:00+00:00',
            '2026-10-23T00:00:00+00:00',
        ));

        self::assertSame('PastDue', $subscription->status());
        self::assertSame('2026-09-23T00:00:00+00:00', $subscription->currentPeriodEnd()->format(DATE_ATOM));
        self::assertSame('2026-09-23T10:01:00+00:00', $subscription->pastDueSince()?->format(DATE_ATOM));
    }

    public function test_reconciliation_applies_provider_state_and_starts_past_due_grace_once(): void
    {
        $subscription = Subscription::activate(new SubscriptionId('subscription-1'), 'plan-1', $this->event(RecurringBillingEventType::Activated, '2026-08-23T10:00:00+00:00'));
        $subscription->reconcile(new ProviderSubscriptionState(
            provider: 'fake', workspaceId: 'workspace-1', providerSubscriptionReference: 'fake-subscription-1', planPriceId: 'price-1',
            status: Subscription::STATUS_PAST_DUE, currentPeriodStart: new \DateTimeImmutable('2026-09-23T00:00:00+00:00'),
            currentPeriodEnd: new \DateTimeImmutable('2026-10-23T00:00:00+00:00'), cancelAtPeriodEnd: false,
            canceledAt: null, observedAt: new \DateTimeImmutable('2026-09-24T10:00:00+00:00'),
        ));

        self::assertSame(Subscription::STATUS_PAST_DUE, $subscription->status());
        self::assertSame('2026-09-24T10:00:00+00:00', $subscription->pastDueSince()?->format(DATE_ATOM));
        self::assertSame(2, $subscription->version());
    }

    private function event(RecurringBillingEventType $type, string $occurredAt): VerifiedRecurringBillingEvent
    {
        return $this->eventWithPeriod(
            $type,
            $occurredAt,
            '2026-08-23T00:00:00+00:00',
            '2026-09-23T00:00:00+00:00',
        );
    }

    private function eventWithPeriod(
        RecurringBillingEventType $type,
        string $occurredAt,
        string $currentPeriodStart,
        string $currentPeriodEnd,
    ): VerifiedRecurringBillingEvent {
        return new VerifiedRecurringBillingEvent(
            provider: 'fake',
            providerEventId: 'event-'.$type->name.'-'.$occurredAt,
            type: $type,
            workspaceId: 'workspace-1',
            providerSubscriptionReference: 'fake-subscription-1',
            planPriceId: 'price-1',
            occurredAt: new \DateTimeImmutable($occurredAt),
            currentPeriodStart: new \DateTimeImmutable($currentPeriodStart),
            currentPeriodEnd: new \DateTimeImmutable($currentPeriodEnd),
            cancelAtPeriodEnd: false,
        );
    }
}
