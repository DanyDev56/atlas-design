<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class WorkspaceCreated implements DomainEvent
{
    public function __construct(
        public WorkspaceId $workspaceId,
        public string $name,
        public string $status,
        public string $accessState,
        public ?string $requestedByUserId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public static function fromWorkspace(Workspace $workspace, EventId $eventId, \DateTimeImmutable $occurredAt): self
    {
        return new self(
            workspaceId: $workspace->id(),
            name: $workspace->name(),
            status: $workspace->status(),
            accessState: $workspace->accessState(),
            requestedByUserId: $workspace->requestedByUserId(),
            eventId: $eventId,
            occurredAt: $occurredAt,
        );
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'workspace.workspace_created';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId->value,
            'name' => $this->name,
            'status' => $this->status,
            'access_state' => $this->accessState,
            'requested_by_user_id' => $this->requestedByUserId,
        ];
    }
}
