<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ClientPrimaryContactChanged implements DomainEvent
{
    public function __construct(
        public ClientId $clientId,
        public string $workspaceId,
        public ?ContactId $previousPrimaryContactId,
        public ?ContactId $newPrimaryContactId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'crm.client_primary_contact_changed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'client_id' => $this->clientId->value,
            'workspace_id' => $this->workspaceId,
            'previous_primary_contact_id' => $this->previousPrimaryContactId?->value,
            'new_primary_contact_id' => $this->newPrimaryContactId?->value,
        ];
    }
}
