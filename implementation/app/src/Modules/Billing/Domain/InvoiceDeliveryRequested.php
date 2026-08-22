<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class InvoiceDeliveryRequested implements DomainEvent
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $workspaceId,
        public int $documentVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'billing.invoice_delivery_requested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoiceId->value,
            'workspace_id' => $this->workspaceId,
            'document_version' => $this->documentVersion,
        ];
    }
}
