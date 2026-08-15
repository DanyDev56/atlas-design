<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class OpportunityUpdated implements DomainEvent
{
    public function __construct(
        public OpportunityId $opportunityId,
        public string $workspaceId,
        public ClientId $clientId,
        public int $version,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'crm.opportunity_updated';
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
            'client_id' => $this->clientId->value,
            'version' => $this->version,
        ];
    }
}
