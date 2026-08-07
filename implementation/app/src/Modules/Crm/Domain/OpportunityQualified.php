<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class OpportunityQualified implements DomainEvent
{
    public function __construct(
        public OpportunityId $opportunityId,
        public string $workspaceId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'crm.opportunity_qualified';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'opportunity_id' => $this->opportunityId->value,
            'workspace_id' => $this->workspaceId,
        ];
    }
}
