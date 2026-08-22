<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class MembershipRestored implements DomainEvent
{
    public function __construct(
        public MembershipId $membershipId,
        public UserId $userId,
        public string $workspaceId,
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
        return 'identity.membership_restored';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'membership_id' => $this->membershipId->value,
            'user_id' => $this->userId->value,
            'workspace_id' => $this->workspaceId,
            'role_id' => $this->roleId->value,
        ];
    }
}
