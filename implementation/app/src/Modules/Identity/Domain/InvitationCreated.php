<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class InvitationCreated implements DomainEvent
{
    public function __construct(
        public string $invitationId,
        public string $workspaceId,
        public string $recipientEmailFingerprint,
        public RoleId $roleId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'identity.invitation_created';
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
            'recipient_email_fingerprint' => $this->recipientEmailFingerprint,
            'role_id' => $this->roleId->value,
        ];
    }
}
