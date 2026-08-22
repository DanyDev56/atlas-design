<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class BillingHistoryImportRequested implements DomainEvent
{
    public function __construct(
        public string $importRunId,
        public string $workspaceId,
        public string $sourceSystem,
        public string $packageHash,
        public int $quoteCount,
        public int $invoiceCount,
        public int $paymentCount,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'billing.history_import_requested';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'import_run_id' => $this->importRunId,
            'workspace_id' => $this->workspaceId,
            'source_system' => $this->sourceSystem,
            'package_hash' => $this->packageHash,
            'quote_count' => $this->quoteCount,
            'invoice_count' => $this->invoiceCount,
            'payment_count' => $this->paymentCount,
        ];
    }
}
