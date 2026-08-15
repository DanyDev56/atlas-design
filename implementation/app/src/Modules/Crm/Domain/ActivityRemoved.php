<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ActivityRemoved implements DomainEvent
{
    public function __construct(
        public ActivityId $activityId,
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
        return 'crm.activity_removed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'activity_id' => $this->activityId->value,
            'workspace_id' => $this->workspaceId,
            'client_id' => $this->clientId->value,
            'version' => $this->version,
        ];
    }
}
