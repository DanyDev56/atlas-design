<?php

declare(strict_types=1);

namespace Atlas\Composition\Crm;

use Atlas\Modules\Crm\Application\ExecuteHistoricalClientsImportHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxHistoricalClientsImportConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly ExecuteHistoricalClientsImportHandler $executeImport,
    ) {}

    public function name(): string
    {
        return 'composition.historical_clients_import';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'crm.client_history_import_requested') {
            return;
        }

        $payload = $message->payload;

        $this->executeImport->handle(
            workspaceId: (string) $payload['workspace_id'],
            importRunId: (string) $payload['import_run_id'],
            correlationId: $message->correlationId,
        );
    }
}
