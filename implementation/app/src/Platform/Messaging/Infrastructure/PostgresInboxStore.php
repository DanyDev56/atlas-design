<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\InboxStore;
use Illuminate\Support\Facades\DB;

final class PostgresInboxStore implements InboxStore
{
    public function hasProcessed(string $consumerName, EventId $eventId): bool
    {
        return DB::table('platform.inbox_receipts')
            ->where('consumer_name', $consumerName)
            ->where('event_id', $eventId->value)
            ->exists();
    }

    public function markProcessed(string $consumerName, EventId $eventId): void
    {
        DB::table('platform.inbox_receipts')->insert([
            'consumer_name' => $consumerName,
            'event_id' => $eventId->value,
            'processed_at' => now()->toIso8601String(),
        ]);
    }
}
