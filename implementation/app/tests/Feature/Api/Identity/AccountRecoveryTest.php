<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class AccountRecoveryTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_recovery_is_opaque_then_resets_password_and_revokes_sessions(): void
    {
        $owner = $this->onboardOwner($this, 'recover-me@test.local');
        $oldToken = $owner['token'];

        $this->postJson('/api/auth/recovery', [
            'email' => 'nobody@test.local',
            'debug_recovery_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonMissingPath('recovery_token');

        $requested = $this->postJson('/api/auth/recovery', [
            'email' => 'recover-me@test.local',
            'debug_recovery_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'accepted');

        $token = $requested->json('recovery_token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->postJson('/api/auth/recovery/complete', [
            'token' => $token,
            'password' => 'NewSecret123!',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'recovered');

        $this->postJson('/api/auth/login', [
            'email' => 'recover-me@test.local',
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'recover-me@test.local',
            'password' => 'NewSecret123!',
        ])->assertOk();

        $this->getJson('/api/auth/session/context', [
            'Authorization' => 'Bearer '.$oldToken,
        ])->assertUnauthorized();

        $this->postJson('/api/auth/recovery/complete', [
            'token' => $token,
            'password' => 'AnotherSecret123!',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Recovery proof invalid.');
    }

    public function test_pending_user_does_not_receive_a_recovery_token(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'pending-recover@test.local',
            'display_name' => 'Pending',
            'password' => 'password123',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson('/api/auth/recovery', [
            'email' => 'pending-recover@test.local',
            'debug_recovery_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonMissingPath('recovery_token');
    }

    public function test_recovery_token_stays_hidden_when_debug_is_disabled(): void
    {
        $this->onboardOwner($this, 'hidden-recover@test.local');
        config()->set('platform.development.debug_verification_tokens', false);

        $this->postJson('/api/auth/recovery', [
            'email' => 'hidden-recover@test.local',
            'debug_recovery_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonMissingPath('recovery_token');
    }
}
