<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class AnalyticsSnapshotPublished implements DomainEvent
{
    public function __construct(
        public string $analyticsSnapshotId,
        public string $workspaceId,
        public string $profileKey,
        public string $profileVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'analytics.snapshot_published';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'analytics_snapshot_id' => $this->analyticsSnapshotId,
            'workspace_id' => $this->workspaceId,
            'snapshot_profile_key' => $this->profileKey,
            'snapshot_profile_version' => $this->profileVersion,
        ];
    }
}
