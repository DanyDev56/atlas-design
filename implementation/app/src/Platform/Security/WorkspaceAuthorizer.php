<?php

declare(strict_types=1);

namespace Atlas\Platform\Security;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Workspace\Domain\Workspace;
use Illuminate\Support\Facades\DB;

final class WorkspaceAuthorizer
{
    public function __construct(
        private readonly PostgresSessionRepository $sessions,
    ) {}

    public function authorize(string $userId, string $workspaceId, string $permission): void
    {
        if (! $this->hasPermission($userId, $workspaceId, $permission)) {
            throw new \DomainException('Unauthorized.');
        }
    }

    public function authorizeElevated(
        string $userId,
        string $workspaceId,
        string $permission,
        string $sessionId,
    ): void {
        $this->authorize($userId, $workspaceId, $permission);

        if (! in_array($permission, $this->sessions->activeElevationPermissions($sessionId), true)) {
            throw new StepUpRequiredException;
        }
    }

    public function hasPermission(string $userId, string $workspaceId, string $permission): bool
    {
        if (! $this->isWorkspaceUsable($workspaceId)) {
            return false;
        }

        $membership = DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.user_id', $userId)
            ->where('m.workspace_id', $workspaceId)
            ->where('m.status', 'Active')
            ->where('r.status', 'Active')
            ->select(['r.permissions'])
            ->first();

        if ($membership === null) {
            return false;
        }

        $permissions = json_decode($membership->permissions, true, 512, JSON_THROW_ON_ERROR);

        return in_array($permission, $permissions, true);
    }

    public function hasActiveMembership(string $userId, string $workspaceId): bool
    {
        if (! $this->isWorkspaceUsable($workspaceId)) {
            return false;
        }

        return DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.user_id', $userId)
            ->where('m.workspace_id', $workspaceId)
            ->where('m.status', 'Active')
            ->where('r.status', 'Active')
            ->exists();
    }

    private function isWorkspaceUsable(string $workspaceId): bool
    {
        $workspace = DB::table('workspace.workspaces')
            ->where('id', $workspaceId)
            ->first();

        return $workspace !== null
            && $workspace->status === Workspace::STATUS_ACTIVE
            && $workspace->access_state === Workspace::ACCESS_ACTIVE;
    }
}
