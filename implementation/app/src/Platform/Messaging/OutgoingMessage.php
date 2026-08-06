<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

final readonly class OutgoingMessage
{
    public function __construct(
        public EventId $eventId,
        public string $eventType,
        public array $payload,
        public \DateTimeImmutable $occurredAt,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public int $schemaVersion = 1,
    ) {}

    public static function fromDomainEvent(
        DomainEvent $event,
        ?string $correlationId = null,
        ?string $causationId = null,
    ): self {
        return new self(
            eventId: $event->eventId(),
            eventType: $event->eventType(),
            payload: $event->payload(),
            occurredAt: $event->occurredAt(),
            correlationId: $correlationId,
            causationId: $causationId,
        );
    }
}
