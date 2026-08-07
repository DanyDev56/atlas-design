<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresAnalyticsSnapshotRepository
{
    /** @param array<string, mixed> $metrics */
    public function insert(
        string $workspaceId,
        string $profileKey,
        string $profileVersion,
        int $generationId,
        \DateTimeImmutable $asOf,
        string $freshnessStatus,
        string $completenessStatus,
        array $metrics,
        array $watermarks,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('analytics.snapshots')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'profile_key' => $profileKey,
            'profile_version' => $profileVersion,
            'generation_id' => $generationId,
            'as_of' => $asOf->format('Y-m-d H:i:sP'),
            'freshness_status' => $freshnessStatus,
            'completeness_status' => $completenessStatus,
            'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
            'watermarks' => json_encode($watermarks, JSON_THROW_ON_ERROR),
            'published_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function findLatest(string $workspaceId, string $profileKey, string $profileVersion): ?array
    {
        $row = DB::table('analytics.snapshots')
            ->where('workspace_id', $workspaceId)
            ->where('profile_key', $profileKey)
            ->where('profile_version', $profileVersion)
            ->orderByDesc('published_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'analytics_snapshot_id' => $row->id,
            'workspace_id' => $row->workspace_id,
            'profile_key' => $row->profile_key,
            'profile_version' => $row->profile_version,
            'generation_id' => (int) $row->generation_id,
            'as_of' => $row->as_of,
            'freshness_status' => $row->freshness_status,
            'completeness_status' => $row->completeness_status,
            'metrics' => json_decode($row->metrics, true, 512, JSON_THROW_ON_ERROR),
            'watermarks' => json_decode($row->watermarks, true, 512, JSON_THROW_ON_ERROR),
            'published_at' => $row->published_at,
        ];
    }
}
