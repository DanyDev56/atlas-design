<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class WorkspaceBillingIdentityUpdated implements DomainEvent
{
    public function __construct(
        public WorkspaceId $workspaceId,
        public int $billingIdentityVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'workspace.workspace_billing_identity_updated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId->value,
            'billing_identity_version' => $this->billingIdentityVersion,
        ];
    }
}
