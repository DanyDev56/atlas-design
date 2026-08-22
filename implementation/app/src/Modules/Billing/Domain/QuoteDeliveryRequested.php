<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class QuoteDeliveryRequested implements DomainEvent
{
    public function __construct(
        public QuoteId $quoteId,
        public string $workspaceId,
        public int $documentVersion,
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
        return 'billing.quote_delivery_requested';
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
            'document_version' => $this->documentVersion,
            'delivery_secret_handle' => $this->deliverySecretHandle,
        ];
    }
}
