<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class MvpJ1OnboardingTest extends IntegrationTestCase
{
    public function test_full_onboarding_from_registration_to_active_workspace(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => 'owner@example.test',
            'display_name' => 'Owner Test',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $register->assertCreated();
        $userId = $register->json('user_id');
        $token = $register->json('verification_token');

        $verify = $this->postJson('/api/auth/verify-email', [
            'user_id' => $userId,
            'token' => $token,
        ]);
        $verify->assertOk()->assertJsonPath('status', 'Active');

        $login = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'password123',
        ]);
        $login->assertOk();
        $sessionToken = $login->json('token');

        $idempotencyKey = (string) Str::uuid();
        $bootstrap = $this->postJson('/api/workspaces/first', [
            'name' => 'Mon activité',
        ], [
            'Authorization' => 'Bearer '.$sessionToken,
            'Idempotency-Key' => $idempotencyKey,
            'X-Correlation-Id' => (string) Str::uuid(),
        ]);

        $bootstrap->assertCreated()
            ->assertJsonPath('status', 'Active')
            ->assertJsonPath('access_state', 'Active');

        $workspaceId = $bootstrap->json('workspace_id');

        $retry = $this->postJson('/api/workspaces/first', [
            'name' => 'Mon activité',
        ], [
            'Authorization' => 'Bearer '.$sessionToken,
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $retry->assertCreated()
            ->assertJsonPath('workspace_id', $workspaceId);

        $this->assertDatabaseHas('identity.memberships', [
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'status' => 'Active',
        ]);
    }
}
