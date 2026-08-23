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

    public function findPrimaryWorkspaceId(UserId $userId): ?string
    {
        $workspaceId = DB::table('identity.memberships')
            ->where('user_id', $userId->value)
            ->where('status', 'Active')
            ->orderBy('created_at')
            ->value('workspace_id');

        return is_string($workspaceId) ? $workspaceId : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(MembershipId $membershipId): ?array
    {
        $row = DB::table('identity.memberships')
            ->where('id', $membershipId->value)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function countActiveOwners(string $workspaceId): int
    {
        return (int) DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.workspace_id', $workspaceId)
            ->where('m.status', 'Active')
            ->where('r.name', 'owner')
            ->where('r.status', 'Active')
            ->count();
    }

    public function countActive(string $workspaceId): int
    {
        return (int) DB::table('identity.memberships')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'Active')
            ->count();
    }

    public function isActiveOwnerMembership(string $membershipId): bool
    {
        return DB::table('identity.memberships as m')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.id', $membershipId)
            ->where('m.status', 'Active')
            ->where('r.name', 'owner')
            ->where('r.status', 'Active')
            ->exists();
    }

    /** @return list<array{membership_id: string, user_id: string, email: string, display_name: string, role: string, status: string}> */
    public function listForWorkspace(string $workspaceId): array
    {
        $rows = DB::table('identity.memberships as m')
            ->join('identity.users as u', 'u.id', '=', 'm.user_id')
            ->join('identity.roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.workspace_id', $workspaceId)
            ->orderBy('m.created_at')
            ->get([
                'm.id as membership_id',
                'm.user_id',
                'u.email',
                'u.display_name',
                'r.name as role',
                'm.status',
            ]);

        return $rows->map(fn ($row): array => [
            'membership_id' => (string) $row->membership_id,
            'user_id' => (string) $row->user_id,
            'email' => (string) $row->email,
            'display_name' => (string) $row->display_name,
            'role' => (string) $row->role,
            'status' => (string) $row->status,
        ])->all();
    }

    public function remove(MembershipId $membershipId, \DateTimeImmutable $removedAt): void
    {
        DB::table('identity.memberships')
            ->where('id', $membershipId->value)
            ->whereIn('status', ['Active', 'Suspended'])
            ->update([
                'status' => 'Removed',
                'version' => DB::raw('version + 1'),
            ]);
    }

    public function restore(
        MembershipId $membershipId,
        RoleId $roleId,
    ): void {
        DB::table('identity.memberships')
            ->where('id', $membershipId->value)
            ->where('status', 'Removed')
            ->update([
                'role_id' => $roleId->value,
                'status' => 'Active',
                'version' => DB::raw('version + 1'),
            ]);
    }
}
