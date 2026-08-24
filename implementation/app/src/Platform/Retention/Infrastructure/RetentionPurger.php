<?php

declare(strict_types=1);

namespace Atlas\Platform\Retention\Infrastructure;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RetentionPurger
{
    /** @var list<string> */
    private const IDEMPOTENCY_TABLES = [
        'identity.idempotency_keys',
        'crm.idempotency_keys',
        'billing.idempotency_keys',
        'analytics.idempotency_keys',
        'business_health.idempotency_keys',
        'advisor.idempotency_keys',
        'notifications.idempotency_keys',
        'operations.operator_action_idempotency',
    ];

    public function purgeAll(bool $dryRun = false): RetentionPurgeResult
    {
        $sessions = $this->purgeSessions(dryRun: $dryRun);
        $outbox = $this->purgeDispatchedOutbox(dryRun: $dryRun);
        $idempotency = $this->purgeIdempotencyKeys(dryRun: $dryRun);

        $result = new RetentionPurgeResult(
            sessions: $sessions,
            outboxDispatched: $outbox,
            idempotencyKeys: $idempotency,
            dryRun: $dryRun,
        );

        Log::info('Retention purge completed', [
            'retention.dry_run' => $dryRun,
            'retention.sessions_deleted' => $sessions,
            'retention.outbox_dispatched_deleted' => $outbox,
            'retention.idempotency_keys_deleted' => $idempotency,
            'retention.sessions_days' => $this->sessionsRetentionDays(),
            'retention.outbox_dispatched_days' => $this->outboxDispatchedRetentionDays(),
            'retention.idempotency_keys_days' => $this->idempotencyRetentionDays(),
        ]);

        return $result;
    }

    public function purgeSessions(?int $days = null, bool $dryRun = false): int
    {
        $cutoff = $this->cutoff($days ?? $this->sessionsRetentionDays());
        $identity = $this->deleteOrCount($this->expiredSessionsQuery('identity.sessions', $cutoff), $dryRun);
        $operator = $this->deleteOrCount($this->expiredSessionsQuery('operations.operator_sessions', $cutoff), $dryRun);

        return $identity + $operator;
    }

    public function purgeDispatchedOutbox(?int $days = null, bool $dryRun = false): int
    {
        $cutoff = $this->cutoff($days ?? $this->outboxDispatchedRetentionDays());
        $query = DB::table('platform.outbox_messages')
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', $cutoff);

        return $this->deleteOrCount($query, $dryRun);
    }

    public function purgeIdempotencyKeys(?int $days = null, bool $dryRun = false): int
    {
        $cutoff = $this->cutoff($days ?? $this->idempotencyRetentionDays());
        $deleted = 0;

        foreach (self::IDEMPOTENCY_TABLES as $table) {
            $query = DB::table($table)->where('created_at', '<', $cutoff);
            $deleted += $this->deleteOrCount($query, $dryRun);
        }

        return $deleted;
    }

    private function expiredSessionsQuery(string $table, string $cutoff): Builder
    {
        return DB::table($table)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('expires_at', '<', $cutoff)
                    ->orWhere(function (Builder $revoked) use ($cutoff): void {
                        $revoked->whereNotNull('revoked_at')
                            ->where('revoked_at', '<', $cutoff);
                    });
            });
    }

    private function deleteOrCount(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    private function cutoff(int $days): string
    {
        return now()->subDays($days)->toIso8601String();
    }

    private function sessionsRetentionDays(): int
    {
        return (int) config('platform.retention.sessions_days', 30);
    }

    private function outboxDispatchedRetentionDays(): int
    {
        return (int) config('platform.retention.outbox_dispatched_days', 90);
    }

    private function idempotencyRetentionDays(): int
    {
        return (int) config('platform.retention.idempotency_keys_days', 30);
    }
}
