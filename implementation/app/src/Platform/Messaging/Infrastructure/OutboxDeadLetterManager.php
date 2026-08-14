<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Illuminate\Support\Facades\DB;

final class OutboxDeadLetterManager
{
    public function retry(string $eventId): bool
    {
        return DB::table('platform.outbox_messages')
            ->where('event_id', $eventId)
            ->whereNotNull('failed_at')
            ->update([
                'attempts' => 0,
                'available_at' => DB::raw('clock_timestamp()'),
                'last_error' => null,
                'failed_at' => null,
            ]) === 1;
    }
}
