<?php

declare(strict_types=1);

namespace Atlas\Platform\Retention\Infrastructure;

use Atlas\Platform\Support\UuidGenerator;
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
        $dataExportArtifacts = $this->purgeExpiredDataExportArtifacts(dryRun: $dryRun);

        $result = new RetentionPurgeResult(
            sessions: $sessions,
            outboxDispatched: $outbox,
            idempotencyKeys: $idempotency,
            dataExportArtifacts: $dataExportArtifacts,
            dryRun: $dryRun,
        );

        Log::info('Retention purge completed', [
            'retention.dry_run' => $dryRun,
            'retention.sessions_deleted' => $sessions,
            'retention.outbox_dispatched_deleted' => $outbox,
            'retention.idempotency_keys_deleted' => $idempotency,
            'retention.data_export_artifacts_deleted' => $dataExportArtifacts,
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

    public function purgeExpiredDataExportArtifacts(bool $dryRun = false): int
    {
        $query = DB::table('operations.data_export_artifacts')->where('expires_at', '<=', now('UTC'));
        if ($dryRun) {
            return $query->count();
        }

        return DB::transaction(function (): int {
            $artifacts = DB::table('operations.data_export_artifacts')
                ->where('expires_at', '<=', now('UTC'))->get(['id', 'data_export_id']);
            $now = now('UTC');
            $deleted = 0;
            foreach ($artifacts as $artifact) {
                $export = DB::table('operations.data_exports')->where('id', $artifact->data_export_id)->lockForUpdate()->first(['status', 'revision']);
                if ($export !== null && in_array((string) $export->status, ['Ready', 'Delivered'], true)) {
                    DB::table('operations.data_exports')->where('id', $artifact->data_export_id)->update([
                        'status' => 'Expired', 'revision' => (int) $export->revision + 1, 'updated_at' => $now,
                    ]);
                    DB::table('operations.data_export_events')->insert([
                        'id' => UuidGenerator::generate(), 'data_export_id' => (string) $artifact->data_export_id,
                        'event_type' => 'Expired', 'status' => 'Expired', 'actor_operator_user_id' => null,
                        'detail_code' => 'retention.artifact-expired', 'occurred_at' => $now, 'created_at' => $now,
                    ]);
                }
                $deleted += DB::table('operations.data_export_artifacts')->where('id', $artifact->id)->where('expires_at', '<=', $now)->delete();
            }

            return $deleted;
        });
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
