<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Modules\Operations\Contracts\OperationsOverviewSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresOperationsOverviewSource implements OperationsOverviewSource
{
    private const EMAIL_EVENT_TYPES = [
        'identity.email_verification_send_requested',
        'identity.account_recovery_send_requested',
        'identity.invitation_send_requested',
        'billing.quote_delivery_requested',
        'billing.invoice_delivery_requested',
        'notifications.advisor_email_delivery_requested',
    ];

    public function outboxSnapshot(\DateTimeImmutable $now): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE failed_at IS NULL AND attempts = 0)::int AS pending_count,
                COUNT(*) FILTER (WHERE failed_at IS NULL AND attempts > 0)::int AS retrying_count,
                COUNT(*) FILTER (WHERE failed_at IS NOT NULL)::int AS dead_letter_count,
                MIN(created_at) FILTER (WHERE failed_at IS NULL) AS oldest_pending_at
            FROM platform.outbox_messages
            WHERE dispatched_at IS NULL
            SQL,
        );

        $oldestPendingAt = $row->oldest_pending_at !== null
            ? new \DateTimeImmutable((string) $row->oldest_pending_at)
            : null;

        return [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'retrying_count' => (int) ($row->retrying_count ?? 0),
            'dead_letter_count' => (int) ($row->dead_letter_count ?? 0),
            'oldest_pending_age_seconds' => $oldestPendingAt !== null
                ? max(0, $now->getTimestamp() - $oldestPendingAt->getTimestamp())
                : null,
        ];
    }

    public function emailSnapshot(): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE delivery.status = 'Accepted')::int AS accepted_count,
                COUNT(*) FILTER (
                    WHERE delivery.event_id IS NULL
                    AND outbox.dispatched_at IS NULL
                    AND outbox.failed_at IS NULL
                    AND outbox.attempts > 0
                )::int AS retrying_count,
                COUNT(*) FILTER (
                    WHERE outbox.dispatched_at IS NULL
                    AND outbox.failed_at IS NOT NULL
                )::int AS failed_count
            FROM platform.outbox_messages AS outbox
            LEFT JOIN platform.email_deliveries AS delivery ON delivery.event_id = outbox.event_id
            WHERE outbox.event_type IN (?, ?, ?, ?, ?, ?)
            SQL,
            self::EMAIL_EVENT_TYPES,
        );

        return [
            'accepted_count' => (int) ($row->accepted_count ?? 0),
            'retrying_count' => (int) ($row->retrying_count ?? 0),
            'failed_count' => (int) ($row->failed_count ?? 0),
        ];
    }

    public function outboxPage(string $status, int $page, int $perPage): array
    {
        $query = DB::table('platform.outbox_messages')
            ->select([
                'event_id',
                'event_type',
                'attempts',
                'created_at',
                'available_at',
                'dispatched_at',
                'failed_at',
            ]);

        $this->applyOutboxStatus($query, $status);
        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $rows->map(static fn (object $row): array => [
            'event_id' => (string) $row->event_id,
            'event_type' => (string) $row->event_type,
            'status' => self::outboxStatus($row),
            'attempts' => (int) $row->attempts,
            'created_at' => (string) $row->created_at,
            'available_at' => $row->available_at !== null ? (string) $row->available_at : null,
            'dispatched_at' => $row->dispatched_at !== null ? (string) $row->dispatched_at : null,
            'failed_at' => $row->failed_at !== null ? (string) $row->failed_at : null,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function emailPage(string $status, int $page, int $perPage): array
    {
        $emailRows = DB::table('platform.outbox_messages as outbox')
            ->leftJoin('platform.email_deliveries as delivery', 'delivery.event_id', '=', 'outbox.event_id')
            ->whereIn('outbox.event_type', self::EMAIL_EVENT_TYPES)
            ->select([
                'outbox.event_id',
                'outbox.event_type',
                'outbox.attempts',
                'outbox.created_at',
                'delivery.template_key',
                'delivery.provider',
                'delivery.updated_at',
                DB::raw(<<<'SQL'
                    CASE
                        WHEN delivery.status IS NOT NULL THEN delivery.status
                        WHEN outbox.failed_at IS NOT NULL THEN 'Failed'
                        WHEN outbox.attempts > 0 THEN 'Retrying'
                        ELSE 'Pending'
                    END AS status
                    SQL),
            ]);

        $query = DB::query()->fromSub($emailRows, 'email_events');
        if ($status !== 'All') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $rows->map(static fn (object $row): array => [
            'event_id' => (string) $row->event_id,
            'event_type' => (string) $row->event_type,
            'template_key' => $row->template_key !== null ? (string) $row->template_key : null,
            'status' => (string) $row->status,
            'attempts' => (int) $row->attempts,
            'provider' => $row->provider !== null ? (string) $row->provider : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => $row->updated_at !== null ? (string) $row->updated_at : null,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    private function applyOutboxStatus(Builder $query, string $status): void
    {
        match ($status) {
            'Pending' => $query->whereNull('dispatched_at')->whereNull('failed_at')->where('attempts', 0),
            'Retrying' => $query->whereNull('dispatched_at')->whereNull('failed_at')->where('attempts', '>', 0),
            'DeadLetter' => $query->whereNull('dispatched_at')->whereNotNull('failed_at'),
            'Dispatched' => $query->whereNotNull('dispatched_at'),
            default => null,
        };
    }

    private static function outboxStatus(object $row): string
    {
        if ($row->dispatched_at !== null) {
            return 'Dispatched';
        }
        if ($row->failed_at !== null) {
            return 'DeadLetter';
        }

        return (int) $row->attempts > 0 ? 'Retrying' : 'Pending';
    }

    /** @param list<array<string, int|string|null>> $items */
    private function page(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
