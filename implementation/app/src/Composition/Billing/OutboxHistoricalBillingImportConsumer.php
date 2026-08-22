<?php

declare(strict_types=1);

namespace Atlas\Composition\Billing;

use Atlas\Modules\Billing\Application\ExecuteHistoricalBillingHistoryImportHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxHistoricalBillingImportConsumer implements OutboxConsumer
{
    public function __construct(private readonly ExecuteHistoricalBillingHistoryImportHandler $executeImport) {}

    public function name(): string
    {
        return 'composition.historical_billing_import';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'billing.history_import_requested') {
            return;
        }

        $this->executeImport->handle(
            workspaceId: (string) $message->payload['workspace_id'],
            runId: (string) $message->payload['import_run_id'],
            correlationId: $message->correlationId,
        );
    }
}
