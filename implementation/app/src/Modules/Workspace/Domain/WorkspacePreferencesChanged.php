<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class WorkspacePreferencesChanged implements DomainEvent
{
    public function __construct(
        public WorkspaceId $workspaceId,
        public int $preferencesVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'workspace.workspace_preferences_changed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId->value,
            'preferences_version' => $this->preferencesVersion,
        ];
    }
}
