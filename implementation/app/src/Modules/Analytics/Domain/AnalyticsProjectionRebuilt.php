<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class AnalyticsProjectionRebuilt implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $generationUuid,
        public int $generationId,
        public string $rebuildReason,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'analytics.projection_rebuilt';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'generation_id' => $this->generationId,
            'rebuild_reason' => $this->rebuildReason,
        ];
    }
}
