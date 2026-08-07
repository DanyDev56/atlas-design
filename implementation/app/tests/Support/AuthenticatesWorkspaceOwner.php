<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

trait AuthenticatesWorkspaceOwner
{
    /** @return array{token: string, user_id: string, workspace_id: string} */
    protected function onboardOwner(IntegrationTestCase $test, string $email = 'owner@crm.test'): array
    {
        $register = $test->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'CRM Owner',
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

        $token = $login->json('token');
        $idempotencyKey = (string) Str::uuid();

        $workspace = $test->postJson('/api/workspaces/first', [
            'name' => 'CRM Workspace',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => $idempotencyKey,
        ])->assertCreated();

        return [
            'token' => $token,
            'user_id' => $login->json('user_id'),
            'workspace_id' => $workspace->json('workspace_id'),
        ];
    }
}
