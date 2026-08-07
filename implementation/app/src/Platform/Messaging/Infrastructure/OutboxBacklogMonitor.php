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
                COUNT(*)::int AS pending_count,
                MIN(created_at) AS oldest_pending_at
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
}
