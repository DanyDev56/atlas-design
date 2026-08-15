<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ContactReactivated implements DomainEvent
{
    public function __construct(
        public ContactId $contactId,
        public ClientId $clientId,
        public string $workspaceId,
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
        return 'crm.contact_reactivated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'contact_id' => $this->contactId->value,
            'client_id' => $this->clientId->value,
            'workspace_id' => $this->workspaceId,
            'version' => $this->version,
        ];
    }
}
