<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class OpportunityWon implements DomainEvent
{
    public function __construct(
        public OpportunityId $opportunityId,
        public string $workspaceId,
        public ClientId $clientId,
        public string $source,
        public ?string $quoteId,
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
        return 'crm.opportunity_won';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'version' => $this->version,
            'client_id' => $this->clientId->value,
            'opportunity_id' => $this->opportunityId->value,
            'workspace_id' => $this->workspaceId,
            'source' => $this->source,
            'quote_id' => $this->quoteId,
        ];
    }
}
