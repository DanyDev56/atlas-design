<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class AdvisorEmailDeliveryRequested implements DomainEvent
{
    public function __construct(
        public string $notificationId,
        public string $workspaceId,
        public string $recipientUserId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'notifications.advisor_email_delivery_requested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'workspace_id' => $this->workspaceId,
            'recipient_user_id' => $this->recipientUserId,
        ];
    }
}
