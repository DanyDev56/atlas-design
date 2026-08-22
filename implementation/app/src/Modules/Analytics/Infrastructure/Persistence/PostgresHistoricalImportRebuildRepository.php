<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresHistoricalImportRebuildRepository
{
    /** @param array<string, mixed> $payload */
    public function recordCompletion(string $kind, array $payload, \DateTimeImmutable $completedAt): void
    {
        $workspaceId = (string) $payload['workspace_id'];
        $sourceSystem = (string) $payload['source_system'];
        $importRunId = (string) $payload['import_run_id'];
        $exists = DB::table('analytics.historical_import_completions')
            ->where('workspace_id', $workspaceId)
            ->where('source_system', $sourceSystem)
            ->where('kind', $kind)
            ->where('import_run_id', $importRunId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('analytics.historical_import_completions')->insert([
            'id' => UuidGenerator::generate(),
            'workspace_id' => $workspaceId,
            'source_system' => $sourceSystem,
            'kind' => $kind,
            'import_run_id' => $importRunId,
            'package_hash' => (string) $payload['package_hash'],
            'completed_at' => $completedAt->format('Y-m-d H:i:sP'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findCompletion(string $workspaceId, string $sourceSystem, string $kind): ?array
    {
        $row = DB::table('analytics.historical_import_completions')
            ->where('workspace_id', $workspaceId)
            ->where('source_system', $sourceSystem)
            ->where('kind', $kind)
            ->orderByDesc('completed_at')
            ->first();

        return $row === null ? null : (array) $row;
    }

    /** @return array<string, mixed>|null */
    public function findRebuild(string $workspaceId, string $crmRunId, string $billingRunId): ?array
    {
        $row = DB::table('analytics.historical_import_rebuilds')
            ->where('workspace_id', $workspaceId)
            ->where('crm_import_run_id', $crmRunId)
            ->where('billing_import_run_id', $billingRunId)
            ->first();

        return $row === null ? null : (array) $row;
    }

    /** @param array<string, mixed> $data */
    public function insertRebuild(array $data): void
    {
        DB::table('analytics.historical_import_rebuilds')->insert($data);
    }

    /** @param array<string, mixed> $values */
    public function updateRebuild(string $rebuildId, array $values): void
    {
        DB::table('analytics.historical_import_rebuilds')->where('id', $rebuildId)->update([
            ...$values,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function nextGenerationId(string $workspaceId): int
    {
        $max = (int) DB::table('analytics.projection_generations')
            ->where('workspace_id', $workspaceId)
            ->max('generation_id');

        return $max + 1;
    }

    /** @param array<string, mixed> $data */
    public function insertGeneration(array $data): void
    {
        DB::table('analytics.projection_generations')->insert($data);
    }

    public function activateGeneration(string $workspaceId, string $generationUuid, \DateTimeImmutable $completedAt): void
    {
        DB::table('analytics.projection_generations')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Active')
            ->update(['status' => 'Superseded']);

        DB::table('analytics.projection_generations')
            ->where('id', $generationUuid)
            ->update([
                'status' => 'Active',
                'completed_at' => $completedAt->format('Y-m-d H:i:sP'),
            ]);
    }

    public function activeGenerationId(string $workspaceId): int
    {
        $value = DB::table('analytics.projection_generations')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Active')
            ->value('generation_id');

        return $value !== null ? (int) $value : 1;
    }
}
