<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class AnalyticsFactRecorded implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $sourceEventId,
        public string $aggregateType,
        public string $aggregateId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'analytics.fact_recorded';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'source_event_id' => $this->sourceEventId,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
        ];
    }
}
