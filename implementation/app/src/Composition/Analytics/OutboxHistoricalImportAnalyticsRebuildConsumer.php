<?php

declare(strict_types=1);

namespace Atlas\Composition\Analytics;

use Atlas\Modules\Analytics\Application\RebuildAnalyticsAfterHistoricalImportHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxHistoricalImportAnalyticsRebuildConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly RebuildAnalyticsAfterHistoricalImportHandler $rebuild,
    ) {}

    public function name(): string
    {
        return 'composition.historical_import_analytics_rebuild';
    }

    public function handle(OutgoingMessage $message): void
    {
        $kind = match ($message->eventType) {
            'crm.client_history_import_completed' => 'crm',
            'billing.history_import_completed' => 'billing',
            default => null,
        };
        if ($kind === null) {
            return;
        }

        $this->rebuild->handleCompletion($kind, $message->payload, $message->correlationId);
    }
}
