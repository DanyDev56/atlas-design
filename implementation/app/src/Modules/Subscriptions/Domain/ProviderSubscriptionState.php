<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

final readonly class ProviderSubscriptionState
{
    public function __construct(
        public string $provider,
        public string $workspaceId,
        public string $providerSubscriptionReference,
        public string $planPriceId,
        public string $status,
        public \DateTimeImmutable $currentPeriodStart,
        public \DateTimeImmutable $currentPeriodEnd,
        public bool $cancelAtPeriodEnd,
        public ?\DateTimeImmutable $canceledAt,
        public \DateTimeImmutable $observedAt,
    ) {
        if (trim($provider) === ''
            || trim($workspaceId) === ''
            || trim($providerSubscriptionReference) === ''
            || trim($planPriceId) === ''
            || ! in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE, Subscription::STATUS_CANCELED], true)
            || ($status === Subscription::STATUS_CANCELED && $canceledAt === null)
            || $currentPeriodEnd <= $currentPeriodStart) {
            throw new \InvalidArgumentException('Invalid provider subscription state.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->provider,
            $this->workspaceId,
            $this->providerSubscriptionReference,
            $this->planPriceId,
            $this->status,
            $this->currentPeriodStart->format(DATE_ATOM),
            $this->currentPeriodEnd->format(DATE_ATOM),
            $this->cancelAtPeriodEnd ? '1' : '0',
            $this->canceledAt?->format(DATE_ATOM) ?? '',
        ]));
    }
}
