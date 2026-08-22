<?php

declare(strict_types=1);

namespace Atlas\Modules\Billing\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostgresDocumentArtifactRepository
{
    public function store(
        string $workspaceId,
        string $documentType,
        string $documentId,
        int $documentVersion,
        string $filename,
        string $content,
    ): void {
        DB::table('billing.document_artifacts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_version' => $documentVersion,
            'filename' => $filename,
            'media_type' => 'application/pdf',
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function latest(string $workspaceId, string $documentType, string $documentId): ?array
    {
        $row = DB::table('billing.document_artifacts')
            ->where('workspace_id', $workspaceId)
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->orderByDesc('document_version')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'artifact_id' => (string) $row->id,
            'document_type' => (string) $row->document_type,
            'document_id' => (string) $row->document_id,
            'document_version' => (int) $row->document_version,
            'filename' => (string) $row->filename,
            'media_type' => (string) $row->media_type,
            'content' => is_resource($row->content) ? stream_get_contents($row->content) : (string) $row->content,
            'content_hash' => (string) $row->content_hash,
            'created_at' => (string) $row->created_at,
        ];
    }
}
