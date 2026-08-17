<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class ClientHistoryImportCompleted implements DomainEvent
{
    public function __construct(
        public string $importRunId,
        public string $workspaceId,
        public string $sourceSystem,
        public string $packageHash,
        public int $clientCount,
        public int $processedCount,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'crm.client_history_import_completed';
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
            'client_count' => $this->clientCount,
            'processed_count' => $this->processedCount,
        ];
    }
}
