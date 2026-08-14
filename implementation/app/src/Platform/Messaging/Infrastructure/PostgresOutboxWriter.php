<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresOutboxWriter implements OutboxWriter
{
    public function append(OutgoingMessage $message): void
    {
        DB::table('platform.outbox_messages')->insert([
            'id' => UuidGenerator::generate(),
            'event_id' => $message->eventId->value,
            'event_type' => $message->eventType,
            'payload' => json_encode($message->payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $message->occurredAt->format('Y-m-d H:i:sP'),
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'schema_version' => $message->schemaVersion,
            'created_at' => DB::raw('clock_timestamp()'),
            'available_at' => DB::raw('clock_timestamp()'),
        ]);
    }
}
