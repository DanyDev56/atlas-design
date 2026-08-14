<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\InboxStore;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Observability\TraceScope;
use Illuminate\Support\Facades\DB;

final class OutboxProcessor
{
    private const string RESULT_DISPATCHED = 'dispatched';

    private const string RESULT_EMPTY = 'empty';

    private const string RESULT_FAILED = 'failed';

    private readonly int $maxAttempts;

    private readonly int $retryBaseSeconds;

    private readonly int $retryMaxSeconds;

    /** @param iterable<OutboxConsumer> $consumers */
    public function __construct(
        private readonly InboxStore $inbox,
        private readonly iterable $consumers,
        private readonly OutboxBacklogMonitor $backlog,
        int $maxAttempts = 5,
        int $retryBaseSeconds = 5,
        int $retryMaxSeconds = 300,
    ) {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->retryBaseSeconds = max(1, $retryBaseSeconds);
        $this->retryMaxSeconds = max($this->retryBaseSeconds, $retryMaxSeconds);
    }

    public function processPending(int $batchSize = 100): int
    {
        $processed = TraceScope::run(
            'outbox.process_pending',
            function () use ($batchSize): int {
                $processed = 0;

                for ($attempted = 0; $attempted < $batchSize; $attempted++) {
                    $result = DB::transaction(fn (): array => $this->processNextWithinTransaction());

                    if ($result['status'] === self::RESULT_EMPTY) {
                        break;
                    }

                    if ($result['status'] === self::RESULT_DISPATCHED) {
                        $processed++;

                        continue;
                    }

                    $this->backlog->reportFailure(
                        eventId: $result['event_id'],
                        eventType: $result['event_type'],
                        attempts: $result['attempts'],
                        maxAttempts: $this->maxAttempts,
                        exceptionType: $result['exception_type'],
                        deadLettered: $result['dead_lettered'],
                    );
                }

                return $processed;
            },
            ['outbox.batch_size' => $batchSize],
        );

        $this->backlog->reportAfterProcessing($processed);

        return $processed;
    }

    /**
     * @return array{
     *     status: string,
     *     event_id?: string,
     *     event_type?: string,
     *     attempts?: int,
     *     exception_type?: string,
     *     dead_lettered?: bool
     * }
     */
    private function processNextWithinTransaction(): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT id, event_id, event_type, payload, occurred_at, correlation_id, causation_id,
                   schema_version, attempts
            FROM platform.outbox_messages
            WHERE dispatched_at IS NULL
              AND failed_at IS NULL
              AND COALESCE(available_at, created_at) <= clock_timestamp()
            ORDER BY created_at
            LIMIT 1
            FOR UPDATE SKIP LOCKED
            SQL,
        );

        if ($row === null) {
            return ['status' => self::RESULT_EMPTY];
        }

        try {
            $this->dispatch($row);

            return ['status' => self::RESULT_DISPATCHED];
        } catch (\Throwable $exception) {
            $attempts = ((int) $row->attempts) + 1;
            $deadLettered = $attempts >= $this->maxAttempts;
            $exceptionType = substr(get_debug_type($exception), 0, 255);
            $updates = [
                'attempts' => $attempts,
                'last_error' => $exceptionType,
            ];

            if ($deadLettered) {
                $updates['failed_at'] = DB::raw('clock_timestamp()');
            } else {
                $retryDelay = $this->retryDelaySeconds($attempts);
                $updates['available_at'] = DB::raw(
                    "clock_timestamp() + make_interval(secs => {$retryDelay})",
                );
            }

            DB::table('platform.outbox_messages')
                ->where('id', $row->id)
                ->update($updates);

            return [
                'status' => self::RESULT_FAILED,
                'event_id' => (string) $row->event_id,
                'event_type' => (string) $row->event_type,
                'attempts' => $attempts,
                'exception_type' => $exceptionType,
                'dead_lettered' => $deadLettered,
            ];
        }
    }

    private function dispatch(object $row): void
    {
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

            DB::transaction(function () use ($consumer, $message): void {
                $consumer->handle($message);
                $this->inbox->markProcessed($consumer->name(), $message->eventId);
            });
        }

        DB::table('platform.outbox_messages')
            ->where('id', $row->id)
            ->update([
                'dispatched_at' => now()->toIso8601String(),
                'last_error' => null,
            ]);
    }

    private function retryDelaySeconds(int $attempts): int
    {
        $exponent = min(max(0, $attempts - 1), 20);

        return min(
            $this->retryMaxSeconds,
            $this->retryBaseSeconds * (2 ** $exponent),
        );
    }
}
