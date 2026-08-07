<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class SessionContextTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_authenticated_user_receives_primary_workspace_id(): void
    {
        $owner = $this->onboardOwner($this, 'context@test');

        $this->getJson('/api/auth/session/context', [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('user_id', $owner['user_id'])
            ->assertJsonPath('workspace_id', $owner['workspace_id']);
    }

    public function test_verified_user_without_workspace_receives_null_workspace_id(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => 'noworkspace@test',
            'display_name' => 'No Workspace',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => 'register-noworkspace',
        ])->assertCreated();

        $this->postJson('/api/auth/verify-email', [
            'user_id' => $register->json('user_id'),
            'token' => $register->json('verification_token'),
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'noworkspace@test',
            'password' => 'password123',
        ])->assertOk();

        $this->getJson('/api/auth/session/context', [
            'Authorization' => 'Bearer '.$login->json('token'),
        ])->assertOk()
            ->assertJsonPath('user_id', $register->json('user_id'))
            ->assertJsonPath('workspace_id', null);
    }
}
