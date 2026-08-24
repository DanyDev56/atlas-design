<?php

declare(strict_types=1);

namespace Atlas\Composition\Operations;

use Atlas\Modules\Operations\Application\GenerateApprovedDataExportHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxWorkspaceDataExportConsumer implements OutboxConsumer
{
    public function __construct(private readonly GenerateApprovedDataExportHandler $generator) {}

    public function name(): string
    {
        return 'composition.workspace_data_export';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'operations.data_export.generation_requested') {
            return;
        }

        $this->generator->handle((string) $message->payload['data_export_id'], $message->correlationId);
    }
}
