<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class OutboxBacklogMonitor
{
    public function __construct(
        private readonly OutboxBacklogAlertNotifier $alerts,
    ) {}

    public function reportAfterProcessing(int $processedInBatch): void
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE failed_at IS NULL)::int AS pending_count,
                COUNT(*) FILTER (WHERE failed_at IS NOT NULL)::int AS dead_letter_count,
                MIN(created_at) FILTER (WHERE failed_at IS NULL) AS oldest_pending_at
            FROM platform.outbox_messages
            WHERE dispatched_at IS NULL
            SQL,
        );

        $pending = (int) ($row->pending_count ?? 0);
        $threshold = (int) config('platform.outbox.backlog_warning_threshold', 25);

        $context = [
            'outbox.pending_count' => $pending,
            'outbox.processed_in_batch' => $processedInBatch,
            'outbox.backlog_warning_threshold' => $threshold,
            'outbox.dead_letter_count' => (int) ($row->dead_letter_count ?? 0),
        ];

        if ($row->oldest_pending_at !== null) {
            $oldest = new \DateTimeImmutable((string) $row->oldest_pending_at);
            $context['outbox.oldest_pending_age_seconds'] = max(
                0,
                (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp() - $oldest->getTimestamp(),
            );
        }

        if ($pending >= $threshold) {
            Log::warning('Outbox backlog above threshold', $context);
            $this->alerts->notifyWarning($context);

            return;
        }

        if ($processedInBatch > 0 || $pending > 0) {
            Log::info('Outbox backlog snapshot', $context);
        }
    }

    public function reportFailure(
        string $eventId,
        string $eventType,
        int $attempts,
        int $maxAttempts,
        string $exceptionType,
        bool $deadLettered,
    ): void {
        $context = [
            'outbox.event_id' => $eventId,
            'outbox.event_type' => $eventType,
            'outbox.attempts' => $attempts,
            'outbox.max_attempts' => $maxAttempts,
            'outbox.exception_type' => $exceptionType,
            'outbox.dead_lettered' => $deadLettered,
        ];

        if ($deadLettered) {
            Log::error('Outbox message dead-lettered', $context);
            $this->alerts->notifyDeadLetter($context);

            return;
        }

        Log::warning('Outbox message scheduled for retry', $context);
    }
}
