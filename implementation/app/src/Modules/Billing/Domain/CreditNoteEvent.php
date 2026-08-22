<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class CreditNoteEvent implements DomainEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private string $type,
        private array $data,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return $this->type;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return $this->data;
    }
}
