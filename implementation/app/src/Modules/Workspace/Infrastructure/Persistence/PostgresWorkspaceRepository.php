<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Infrastructure\Persistence;

use Atlas\Modules\Workspace\Domain\Workspace;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Illuminate\Support\Facades\DB;

final class PostgresWorkspaceRepository implements WorkspaceRepository
{
    public function save(Workspace $workspace): void
    {
        DB::table('workspace.workspaces')->updateOrInsert(
            ['id' => $workspace->id()->value],
            [
                'name' => $workspace->name(),
                'status' => $workspace->status(),
                'access_state' => $workspace->accessState(),
                'version' => $workspace->version(),
                'governance_version' => $workspace->governanceVersion(),
                'requested_by_user_id' => $workspace->requestedByUserId(),
                'created_at' => $workspace->createdAt()->format('Y-m-d H:i:sP'),
                'updated_at' => $workspace->updatedAt()->format('Y-m-d H:i:sP'),
                'activated_at' => $workspace->activatedAt()?->format('Y-m-d H:i:sP'),
            ],
        );
    }

    public function findById(WorkspaceId $id): ?Workspace
    {
        $row = DB::table('workspace.workspaces')
            ->where('id', $id->value)
            ->first();

        if ($row === null) {
            return null;
        }

        return Workspace::reconstitute((array) $row);
    }
}
