<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\InboxStore;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Observability\TraceScope;
use Illuminate\Support\Facades\DB;

final class OutboxProcessor
{
    /** @param iterable<OutboxConsumer> $consumers */
    public function __construct(
        private readonly InboxStore $inbox,
        private readonly iterable $consumers,
    ) {}

    public function processPending(int $batchSize = 100): int
    {
        return TraceScope::run(
            'outbox.process_pending',
            fn (): int => (int) DB::transaction(function () use ($batchSize): int {
                return $this->processPendingWithinTransaction($batchSize);
            }),
            ['outbox.batch_size' => $batchSize],
        );
    }

    private function processPendingWithinTransaction(int $batchSize): int
    {
        $processed = 0;

        $rows = DB::select(
            <<<'SQL'
            SELECT id, event_id, event_type, payload, occurred_at, correlation_id, causation_id, schema_version
            FROM platform.outbox_messages
            WHERE dispatched_at IS NULL
            ORDER BY created_at
            LIMIT ?
            FOR UPDATE SKIP LOCKED
            SQL,
            [$batchSize],
        );

        foreach ($rows as $row) {
            $message = new OutgoingMessage(
                eventId: new EventId($row->event_id),
                eventType: $row->event_type,
                payload: json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR),
                occurredAt: new \DateTimeImmutable($row->occurred_at),
                correlationId: $row->correlation_id,
                causationId: $row->causation_id,
                schemaVersion: (int) $row->schema_version,
            );

            foreach ($this->consumers as $consumer) {
                if ($this->inbox->hasProcessed($consumer->name(), $message->eventId)) {
                    continue;
                }

                $consumer->handle($message);
                $this->inbox->markProcessed($consumer->name(), $message->eventId);
            }

            DB::table('platform.outbox_messages')
                ->where('id', $row->id)
                ->update(['dispatched_at' => now()->toIso8601String()]);

            $processed++;
        }

        return $processed;
    }
}
