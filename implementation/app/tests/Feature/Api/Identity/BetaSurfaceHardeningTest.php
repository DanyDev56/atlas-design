<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class BetaSurfaceHardeningTest extends IntegrationTestCase
{
    public function test_registration_rejects_a_display_name_matching_the_password(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'password-as-name@example.test',
            'display_name' => 'SameValue2026!',
            'password' => 'SameValue2026!',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Le nom affiché doit être différent du mot de passe.');

        $this->assertDatabaseMissing('identity.users', [
            'email' => 'password-as-name@example.test',
        ]);
    }

    public function test_development_routes_are_hidden_when_disabled(): void
    {
        config()->set('platform.development.routes_enabled', false);

        $this->postJson('/api/spike/workspaces', [
            'name' => 'Hidden Workspace',
        ])->assertNotFound();

        $this->postJson('/api/dev/outbox/process')->assertNotFound();
    }

    public function test_registration_never_exposes_debug_token_when_disabled(): void
    {
        config()->set('platform.development.debug_verification_tokens', false);

        $this->postJson('/api/auth/register', [
            'email' => 'no-debug-token@example.test',
            'display_name' => 'No Debug Token',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonMissingPath('verification_token');
    }
}
