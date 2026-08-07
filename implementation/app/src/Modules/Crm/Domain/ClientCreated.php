<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ClientCreated implements DomainEvent
{
    public function __construct(
        public ClientId $clientId,
        public string $workspaceId,
        public string $kind,
        public string $status,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'crm.client_created';
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
            'kind' => $this->kind,
            'status' => $this->status,
        ];
    }
}
