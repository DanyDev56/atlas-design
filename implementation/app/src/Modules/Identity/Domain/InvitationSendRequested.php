<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class InvitationSendRequested implements DomainEvent
{
    public function __construct(
        public string $invitationId,
        public string $workspaceId,
        public string $deliverySecretHandle,
        public string $expiresAt,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'identity.invitation_send_requested';
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
            'delivery_secret_handle' => $this->deliverySecretHandle,
            'expires_at' => $this->expiresAt,
        ];
    }
}
