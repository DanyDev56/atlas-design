<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresAnalyticsFactRepository
{
    public function exists(string $workspaceId, string $sourceEventId): bool
    {
        return DB::table('analytics.source_facts')
            ->where('workspace_id', $workspaceId)
            ->where('source_event_id', $sourceEventId)
            ->exists();
    }

    /** @param array<string, mixed> $payload */
    public function insert(
        string $workspaceId,
        string $sourceEventId,
        string $sourceEventType,
        string $aggregateType,
        string $aggregateId,
        int $aggregateVersion,
        string $factHash,
        array $payload,
        \DateTimeImmutable $sourceOccurredAt,
    ): void {
        DB::table('analytics.source_facts')->insert([
            'id' => UuidGenerator::generate(),
            'workspace_id' => $workspaceId,
            'source_event_id' => $sourceEventId,
            'source_event_type' => $sourceEventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $aggregateVersion,
            'fact_hash' => $factHash,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_occurred_at' => $sourceOccurredAt->format('Y-m-d H:i:sP'),
            'ingested_at' => now()->toIso8601String(),
        ]);
    }

    public function updateWatermark(string $workspaceId, string $sourceKind, \DateTimeImmutable $completeThrough): void
    {
        $existing = DB::table('analytics.watermarks')
            ->where('workspace_id', $workspaceId)
            ->where('source_kind', $sourceKind)
            ->first();

        if ($existing === null) {
            DB::table('analytics.watermarks')->insert([
                'workspace_id' => $workspaceId,
                'source_kind' => $sourceKind,
                'complete_through' => $completeThrough->format('Y-m-d H:i:sP'),
                'updated_at' => now()->toIso8601String(),
            ]);

            return;
        }

        $current = new \DateTimeImmutable($existing->complete_through);
        if ($completeThrough > $current) {
            DB::table('analytics.watermarks')
                ->where('workspace_id', $workspaceId)
                ->where('source_kind', $sourceKind)
                ->update([
                    'complete_through' => $completeThrough->format('Y-m-d H:i:sP'),
                    'updated_at' => now()->toIso8601String(),
                ]);
        }
    }

    /** @return array<string, mixed>|null */
    public function getWatermark(string $workspaceId, string $sourceKind): ?array
    {
        $row = DB::table('analytics.watermarks')
            ->where('workspace_id', $workspaceId)
            ->where('source_kind', $sourceKind)
            ->first();

        return $row !== null ? (array) $row : null;
    }
}
