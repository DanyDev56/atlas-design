<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Modules\Identity\Domain\MembershipId;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Domain\UserId;
use Illuminate\Support\Facades\DB;

final class PostgresMembershipRepository
{
    public function create(
        MembershipId $membershipId,
        UserId $userId,
        string $workspaceId,
        RoleId $roleId,
        \DateTimeImmutable $now,
    ): void {
        DB::table('identity.memberships')->insert([
            'id' => $membershipId->value,
            'user_id' => $userId->value,
            'workspace_id' => $workspaceId,
            'role_id' => $roleId->value,
            'status' => 'Active',
            'version' => 1,
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    public function hasActiveOwner(string $workspaceId): bool
    {
        return DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.workspace_id', $workspaceId)
            ->where('m.status', 'Active')
            ->where('r.name', 'owner')
            ->where('r.status', 'Active')
            ->exists();
    }

    public function findByUserAndWorkspace(UserId $userId, string $workspaceId): ?array
    {
        $row = DB::table('identity.memberships')
            ->where('user_id', $userId->value)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row !== null ? (array) $row : null;
    }
}
