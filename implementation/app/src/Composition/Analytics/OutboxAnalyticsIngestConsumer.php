<?php

declare(strict_types=1);

namespace Atlas\Composition\Analytics;

use Atlas\Modules\Analytics\Application\IngestSourceFactHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxAnalyticsIngestConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly IngestSourceFactHandler $ingest,
    ) {}

    public function name(): string
    {
        return 'composition.analytics_ingest';
    }

    public function handle(OutgoingMessage $message): void
    {
        $this->ingest->handle($message);
    }
}
