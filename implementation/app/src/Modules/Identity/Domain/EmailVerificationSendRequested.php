<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class EmailVerificationSendRequested implements DomainEvent
{
    public function __construct(
        public UserId $userId,
        public string $deliverySecretHandle,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'identity.email_verification_send_requested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->userId->value,
            'delivery_secret_handle' => $this->deliverySecretHandle,
        ];
    }
}
