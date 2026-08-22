<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class InvoiceOverdue implements DomainEvent
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $workspaceId,
        public string $dueDate,
        public int $outstandingBalanceCents,
        public int $aggregateVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'billing.invoice_overdue';
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
            'due_date' => $this->dueDate,
            'outstanding_balance_cents' => $this->outstandingBalanceCents,
            'aggregate_version' => $this->aggregateVersion,
        ];
    }
}
