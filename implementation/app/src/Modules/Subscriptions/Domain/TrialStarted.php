<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class TrialStarted implements DomainEvent
{
    public function __construct(
        public TrialId $trialId,
        public string $workspaceId,
        public string $planId,
        public \DateTimeImmutable $endsAt,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'subscriptions.trial_started';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'trial_id' => $this->trialId->value,
            'workspace_id' => $this->workspaceId,
            'plan_id' => $this->planId,
            'ends_at' => $this->endsAt->format(DATE_ATOM),
        ];
    }
}
