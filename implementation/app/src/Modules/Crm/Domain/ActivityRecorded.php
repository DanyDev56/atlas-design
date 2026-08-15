<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ActivityRecorded implements DomainEvent
{
    public function __construct(
        public ActivityId $activityId,
        public string $workspaceId,
        public ClientId $clientId,
        public string $kind,
        public \DateTimeImmutable $activityOccurredAt,
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
        return 'crm.activity_recorded';
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
            'kind' => $this->kind,
            'activity_occurred_at' => $this->activityOccurredAt->format(DATE_ATOM),
            'version' => $this->version,
        ];
    }
}
