<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

interface DomainEvent
{
    public function eventId(): EventId;

    public function eventType(): string;

    public function occurredAt(): \DateTimeImmutable;

    /** @return array<string, mixed> */
    public function payload(): array;
}
