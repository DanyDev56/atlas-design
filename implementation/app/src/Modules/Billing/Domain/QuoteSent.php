<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class QuoteSent implements DomainEvent
{
    public function __construct(
        public QuoteId $quoteId,
        public string $workspaceId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'billing.quote_sent';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'quote_id' => $this->quoteId->value,
            'workspace_id' => $this->workspaceId,
        ];
    }
}
