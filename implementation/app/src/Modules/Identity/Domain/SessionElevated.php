<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class SessionElevated implements DomainEvent
{
    public function __construct(
        public SessionId $sessionId,
        public UserId $userId,
        public string $elevationScope,
        public \DateTimeImmutable $elevationExpiresAt,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'identity.session_elevated';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId->value,
            'user_id' => $this->userId->value,
            'elevation_scope' => $this->elevationScope,
            'elevation_expires_at' => $this->elevationExpiresAt->format(DATE_ATOM),
        ];
    }
}
