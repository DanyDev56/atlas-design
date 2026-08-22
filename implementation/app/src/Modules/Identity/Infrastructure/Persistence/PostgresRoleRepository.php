<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Modules\Identity\Domain\RoleId;
use Illuminate\Support\Facades\DB;

final class PostgresRoleRepository
{
    /** @param list<string> $permissions */
    public function createRole(
        RoleId $roleId,
        string $workspaceId,
        string $name,
        array $permissions,
        \DateTimeImmutable $now,
    ): void {
        DB::table('identity.roles')->insert([
            'id' => $roleId->value,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'status' => 'Active',
            'version' => 1,
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    /** @param list<string> $permissions */
    public function createOwnerRole(RoleId $roleId, string $workspaceId, array $permissions, \DateTimeImmutable $now): void
    {
        $this->createRole($roleId, $workspaceId, 'owner', $permissions, $now);
    }

    /** @param list<string> $permissions */
    public function replacePermissions(RoleId $roleId, array $permissions): void
    {
        DB::table('identity.roles')
            ->where('id', $roleId->value)
            ->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'version' => DB::raw('version + 1'),
            ]);
    }

    public function findOwnerRoleId(string $workspaceId): ?string
    {
        return DB::table('identity.roles')
            ->where('workspace_id', $workspaceId)
            ->where('name', 'owner')
            ->value('id');
    }
}
