<?php

declare(strict_types=1);

namespace Tests\Support;

use Atlas\Modules\Identity\Domain\MembershipId;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresRoleRepository;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

trait AddsWorkspaceMember
{
    /** @return array{token: string, user_id: string, membership_id: string} */
    protected function addMemberToWorkspace(
        IntegrationTestCase $test,
        string $workspaceId,
        string $email = 'member@crm.test',
    ): array {
        $register = $test->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Workspace Member',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $test->postJson('/api/auth/verify-email', [
            'user_id' => $register->json('user_id'),
            'token' => $register->json('verification_token'),
        ])->assertOk();

        $login = $test->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $roleId = RoleId::generate();
        app(PostgresRoleRepository::class)->createRole(
            $roleId,
            $workspaceId,
            'member',
            ['crm.clients.read', 'crm.clients.create'],
            $now,
        );

        $membershipId = MembershipId::generate();
        app(PostgresMembershipRepository::class)->create(
            membershipId: $membershipId,
            userId: new UserId($login->json('user_id')),
            workspaceId: $workspaceId,
            roleId: $roleId,
            now: $now,
        );

        return [
            'token' => $login->json('token'),
            'user_id' => $login->json('user_id'),
            'membership_id' => $membershipId->value,
        ];
    }
}
