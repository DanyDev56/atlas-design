<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class PaymentRecorded implements DomainEvent
{
    public function __construct(
        public PaymentId $paymentId,
        public InvoiceId $invoiceId,
        public string $workspaceId,
        public int $amountCents,
        public int $remainingBalanceCents,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'billing.payment_recorded';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->paymentId->value,
            'invoice_id' => $this->invoiceId->value,
            'workspace_id' => $this->workspaceId,
            'amount_cents' => $this->amountCents,
            'remaining_balance_cents' => $this->remainingBalanceCents,
        ];
    }
}
