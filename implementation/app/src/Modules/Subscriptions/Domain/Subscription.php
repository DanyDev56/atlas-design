<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final class Subscription
{
    public const string STATUS_ACTIVE = 'Active';

    public const string STATUS_PAST_DUE = 'PastDue';

    public const string STATUS_CANCELED = 'Canceled';

    private function __construct(
        private readonly SubscriptionId $id,
        private readonly string $workspaceId,
        private string $planId,
        private string $planPriceId,
        private string $provider,
        private string $providerReference,
        private string $status,
        private \DateTimeImmutable $currentPeriodStart,
        private \DateTimeImmutable $currentPeriodEnd,
        private bool $cancelAtPeriodEnd,
        private ?\DateTimeImmutable $canceledAt,
        private \DateTimeImmutable $lastProviderEventAt,
        private int $version,
    ) {}

    public static function activate(
        SubscriptionId $id,
        string $planId,
        VerifiedRecurringBillingEvent $event,
    ): self {
        if ($event->type !== RecurringBillingEventType::Activated) {
            throw new \DomainException('Subscription activation required.');
        }

        return new self(
            id: $id,
            workspaceId: $event->workspaceId,
            planId: $planId,
            planPriceId: $event->planPriceId,
            provider: $event->provider,
            providerReference: $event->providerSubscriptionReference,
            status: self::STATUS_ACTIVE,
            currentPeriodStart: $event->currentPeriodStart,
            currentPeriodEnd: $event->currentPeriodEnd,
            cancelAtPeriodEnd: $event->cancelAtPeriodEnd,
            canceledAt: null,
            lastProviderEventAt: $event->occurredAt,
            version: 1,
        );
    }

    /** @param array<string, mixed> $row */
    public static function reconstitute(array $row): self
    {
        return new self(
            id: new SubscriptionId((string) $row['id']),
            workspaceId: (string) $row['workspace_id'],
            planId: (string) $row['plan_id'],
            planPriceId: (string) $row['plan_price_id'],
            provider: (string) $row['provider'],
            providerReference: (string) $row['provider_subscription_reference'],
            status: (string) $row['status'],
            currentPeriodStart: new \DateTimeImmutable((string) $row['current_period_start']),
            currentPeriodEnd: new \DateTimeImmutable((string) $row['current_period_end']),
            cancelAtPeriodEnd: (bool) $row['cancel_at_period_end'],
            canceledAt: isset($row['canceled_at']) ? new \DateTimeImmutable((string) $row['canceled_at']) : null,
            lastProviderEventAt: new \DateTimeImmutable((string) $row['last_provider_event_at']),
            version: (int) $row['version'],
        );
    }

    public function apply(VerifiedRecurringBillingEvent $event): bool
    {
        if (
            $event->provider !== $this->provider
            || $event->providerSubscriptionReference !== $this->providerReference
            || $event->workspaceId !== $this->workspaceId
            || $event->planPriceId !== $this->planPriceId
        ) {
            throw new \DomainException('Subscription webhook target mismatch.');
        }
        if ($event->occurredAt <= $this->lastProviderEventAt) {
            return false;
        }

        $this->status = match ($event->type) {
            RecurringBillingEventType::Activated, RecurringBillingEventType::Renewed => self::STATUS_ACTIVE,
            RecurringBillingEventType::PaymentFailed => self::STATUS_PAST_DUE,
            RecurringBillingEventType::Canceled => self::STATUS_CANCELED,
        };
        $this->currentPeriodStart = $event->currentPeriodStart;
        $this->currentPeriodEnd = $event->currentPeriodEnd;
        $this->cancelAtPeriodEnd = $event->cancelAtPeriodEnd || $event->type === RecurringBillingEventType::Canceled;
        $this->canceledAt = $event->type === RecurringBillingEventType::Canceled ? $event->occurredAt : null;
        $this->lastProviderEventAt = $event->occurredAt;
        $this->version++;

        return true;
    }

    public function resubscribe(string $planId, VerifiedRecurringBillingEvent $event): void
    {
        if ($this->status !== self::STATUS_CANCELED) {
            throw new \DomainException('Only a canceled subscription can be replaced.');
        }
        if ($event->type !== RecurringBillingEventType::Activated) {
            throw new \DomainException('Subscription activation required.');
        }
        if ($event->workspaceId !== $this->workspaceId || $event->occurredAt <= $this->lastProviderEventAt) {
            throw new \DomainException('Invalid subscription replacement.');
        }

        $this->planId = $planId;
        $this->planPriceId = $event->planPriceId;
        $this->provider = $event->provider;
        $this->providerReference = $event->providerSubscriptionReference;
        $this->status = self::STATUS_ACTIVE;
        $this->currentPeriodStart = $event->currentPeriodStart;
        $this->currentPeriodEnd = $event->currentPeriodEnd;
        $this->cancelAtPeriodEnd = $event->cancelAtPeriodEnd;
        $this->canceledAt = null;
        $this->lastProviderEventAt = $event->occurredAt;
        $this->version++;
    }

    public function id(): SubscriptionId
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function planId(): string
    {
        return $this->planId;
    }

    public function planPriceId(): string
    {
        return $this->planPriceId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function providerReference(): string
    {
        return $this->providerReference;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function currentPeriodStart(): \DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function currentPeriodEnd(): \DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function cancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function canceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function lastProviderEventAt(): \DateTimeImmutable
    {
        return $this->lastProviderEventAt;
    }

    public function version(): int
    {
        return $this->version;
    }
}
