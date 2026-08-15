<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ClientProfileUpdated implements DomainEvent
{
    public function __construct(
        public ClientId $clientId,
        public string $workspaceId,
        public int $profileVersion,
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
        return 'crm.client_profile_updated';
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
            'profile_version' => $this->profileVersion,
            'version' => $this->version,
        ];
    }
}
