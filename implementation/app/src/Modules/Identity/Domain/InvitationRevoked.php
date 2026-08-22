<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class InvitationRevoked implements DomainEvent
{
    public function __construct(
        public string $invitationId,
        public string $workspaceId,
        public UserId $revokedBy,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'identity.invitation_revoked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'invitation_id' => $this->invitationId,
            'workspace_id' => $this->workspaceId,
            'revoked_by' => $this->revokedBy->value,
        ];
    }
}
