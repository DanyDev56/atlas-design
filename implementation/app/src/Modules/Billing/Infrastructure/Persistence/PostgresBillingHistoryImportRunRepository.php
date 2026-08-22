<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresBillingHistoryImportRunRepository
{
    /** @param array<string, mixed> $data */
    public function insert(array $data): void
    {
        DB::table('billing.history_import_runs')->insert($data);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $workspaceId, string $runId): ?array
    {
        $row = DB::table('billing.history_import_runs')
            ->where('workspace_id', $workspaceId)->where('id', $runId)->first();

        return $row === null ? null : (array) $row;
    }

    /** @return array<string, mixed>|null */
    public function findByPackageHash(string $workspaceId, string $packageHash): ?array
    {
        $row = DB::table('billing.history_import_runs')
            ->where('workspace_id', $workspaceId)
            ->where('package_hash', $packageHash)
            ->whereIn('status', ['Processing', 'Completed'])
            ->orderBy('created_at')
            ->first();

        return $row === null ? null : (array) $row;
    }

    /** @param array<string, mixed> $values */
    public function update(string $runId, array $values): void
    {
        DB::table('billing.history_import_runs')->where('id', $runId)->update([
            ...$values,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
