<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class SqlModuleIsolationTest extends IntegrationTestCase
{
    public function test_module_roles_cannot_cross_schemas(): void
    {
        if (config('database.connections.pgsql.username') !== 'atlas') {
            $this->markTestSkipped('Module roles are only enforced when connecting as atlas.');
        }

        if (! $this->roleExists('atlas_workspace')) {
            $this->markTestSkipped('Module role atlas_workspace is not provisioned (recreate the Postgres volume).');
        }

        $this->assertFalse($this->tryWorkspaceRoleAccess());

        if ($this->roleExists('atlas_operations')) {
            $this->assertFalse($this->tryOperationsRoleAccess());
        }
    }

    private function roleExists(string $role): bool
    {
        return DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role]) !== null;
    }

    private function tryWorkspaceRoleAccess(): bool
    {
        DB::connection('pgsql')->statement('SAVEPOINT atlas_role_isolation_test');

        try {
            DB::connection('pgsql')->statement('SET LOCAL ROLE atlas_workspace');
            DB::connection('pgsql')->select('SELECT 1 FROM platform.outbox_messages LIMIT 1');
            DB::connection('pgsql')->statement('RELEASE SAVEPOINT atlas_role_isolation_test');

            return true;
        } catch (\Throwable) {
            DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT atlas_role_isolation_test');

            return false;
        }
    }

    private function tryOperationsRoleAccess(): bool
    {
        DB::connection('pgsql')->statement('SAVEPOINT atlas_operations_role_isolation_test');

        try {
            DB::connection('pgsql')->statement('SET LOCAL ROLE atlas_operations');
            DB::connection('pgsql')->select('SELECT 1 FROM identity.users LIMIT 1');
            DB::connection('pgsql')->statement('RELEASE SAVEPOINT atlas_operations_role_isolation_test');

            return true;
        } catch (\Throwable) {
            DB::connection('pgsql')->statement('ROLLBACK TO SAVEPOINT atlas_operations_role_isolation_test');

            return false;
        }
    }
}
