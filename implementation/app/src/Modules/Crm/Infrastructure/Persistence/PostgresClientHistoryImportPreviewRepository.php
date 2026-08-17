<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresClientHistoryImportPreviewRepository
{
    /** @param array<string, mixed> $preview */
    public function insert(array $preview): void
    {
        DB::table('crm.client_history_import_previews')->insert([
            ...$preview,
            'records' => json_encode($preview['records'], JSON_THROW_ON_ERROR),
            'validation_errors' => json_encode($preview['validation_errors'], JSON_THROW_ON_ERROR),
            'duplicate_candidates' => json_encode($preview['duplicate_candidates'], JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $workspaceId, string $previewId): ?array
    {
        $row = DB::table('crm.client_history_import_previews')
            ->where('workspace_id', $workspaceId)
            ->where('id', $previewId)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'workspace_id' => $row->workspace_id,
            'source_system' => $row->source_system,
            'source_exported_at' => $row->source_exported_at,
            'schema_version' => $row->schema_version,
            'package_hash' => $row->package_hash,
            'row_count' => (int) $row->row_count,
            'valid_row_count' => (int) $row->valid_row_count,
            'validation_error_count' => (int) $row->validation_error_count,
            'duplicate_candidate_count' => property_exists($row, 'duplicate_candidate_count') ? (int) $row->duplicate_candidate_count : 0,
            'valid_for_confirmation' => property_exists($row, 'valid_for_confirmation') ? (bool) $row->valid_for_confirmation : true,
            'records' => json_decode((string) $row->records, true, 512, JSON_THROW_ON_ERROR),
            'validation_errors' => json_decode((string) $row->validation_errors, true, 512, JSON_THROW_ON_ERROR),
            'duplicate_candidates' => json_decode((string) $row->duplicate_candidates, true, 512, JSON_THROW_ON_ERROR),
            'created_by' => $row->created_by,
            'expires_at' => $row->expires_at,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @param  list<string>  $normalizedNames
     * @return list<array{id: string, display_name: string}>
     */
    public function findClientsByNormalizedDisplayNames(string $workspaceId, array $normalizedNames): array
    {
        if ($normalizedNames === []) {
            return [];
        }

        return DB::table('crm.clients')
            ->select(['id', 'display_name'])
            ->where('workspace_id', $workspaceId)
            ->whereIn(DB::raw('LOWER(display_name)'), array_values(array_unique($normalizedNames)))
            ->get()
            ->map(fn($row): array => ['id' => $row->id, 'display_name' => $row->display_name])
            ->all();
    }
}
