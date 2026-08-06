<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging;

interface InboxStore
{
    public function hasProcessed(string $consumerName, EventId $eventId): bool;

    public function markProcessed(string $consumerName, EventId $eventId): void;
}
