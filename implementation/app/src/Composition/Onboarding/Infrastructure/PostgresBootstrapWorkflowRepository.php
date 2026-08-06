<?php

declare(strict_types=1);

namespace Atlas\Composition\Onboarding\Infrastructure;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresBootstrapWorkflowRepository
{
    /** @return array<string, mixed>|null */
    public function findByIdempotencyKey(string $key): ?array
    {
        $row = DB::table('identity.bootstrap_workflows')
            ->where('idempotency_key', $key)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function create(string $userId, string $idempotencyKey): string
    {
        $id = UuidGenerator::generate();

        DB::table('identity.bootstrap_workflows')->insert([
            'id' => $id,
            'user_id' => $userId,
            'workspace_id' => null,
            'idempotency_key' => $idempotencyKey,
            'status' => 'started',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    public function attachWorkspace(string $workflowId, string $workspaceId, string $status): void
    {
        DB::table('identity.bootstrap_workflows')
            ->where('id', $workflowId)
            ->update([
                'workspace_id' => $workspaceId,
                'status' => $status,
                'updated_at' => now()->toIso8601String(),
            ]);
    }

    public function updateStatus(string $workflowId, string $status): void
    {
        DB::table('identity.bootstrap_workflows')
            ->where('id', $workflowId)
            ->update([
                'status' => $status,
                'updated_at' => now()->toIso8601String(),
            ]);
    }
}
