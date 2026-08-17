<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresClientHistoryImportRunRepository
{
    public function insert(array $data): void
    {
        DB::table('crm.client_history_import_runs')->insert([
            'id' => $data['id'],
            'workspace_id' => $data['workspace_id'],
            'preview_id' => $data['preview_id'],
            'source_system' => $data['source_system'],
            'source_exported_at' => $data['source_exported_at'],
            'package_hash' => $data['package_hash'],
            'status' => $data['status'],
            'client_count' => $data['client_count'],
            'processed_count' => $data['processed_count'],
            'created_by' => $data['created_by'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
        ]);
    }

    public function findById(string $workspaceId, string $importRunId): ?array
    {
        $row = DB::table('crm.client_history_import_runs')
            ->where('workspace_id', $workspaceId)
            ->where('id', $importRunId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function updateProcessedCount(string $importRunId, int $processedCount): void
    {
        DB::table('crm.client_history_import_runs')
            ->where('id', $importRunId)
            ->update([
                'processed_count' => $processedCount,
                'updated_at' => now(),
            ]);
    }

    public function markCompleted(string $importRunId): void
    {
        DB::table('crm.client_history_import_runs')
            ->where('id', $importRunId)
            ->update([
                'status' => 'Completed',
                'updated_at' => now(),
            ]);
    }
}
