<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\ElevatesSession;

final class ElevateSessionTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;
    use ElevatesSession;

    public function test_owner_can_elevate_the_current_session_with_password(): void
    {
        $owner = $this->onboardOwner($this, 'elevate@test.local');

        $this->postJson('/api/auth/session/elevate', [
            'password' => 'password123',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('session_id', fn ($value): bool => is_string($value) && Str::isUuid($value))
            ->assertJsonPath('elevation_scope', 'PermissionScoped')
            ->assertJsonPath('elevation_expires_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->getJson('/api/auth/session/context', [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('elevation_expires_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'identity.session_elevated')->count());
    }

    public function test_wrong_password_does_not_elevate_or_create_a_session(): void
    {
        $owner = $this->onboardOwner($this, 'elevate-wrong@test.local');
        $sessionCount = DB::table('identity.sessions')->where('user_id', $owner['user_id'])->count();

        $this->postJson('/api/auth/session/elevate', [
            'password' => 'not-the-password',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnauthorized()
            ->assertJsonPath('messages.0', 'Invalid credentials.');

        $this->assertSame($sessionCount, DB::table('identity.sessions')->where('user_id', $owner['user_id'])->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'identity.session_elevated')->count());
        $this->getJson('/api/auth/session/context', [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()->assertJsonPath('elevation_expires_at', null);
    }

    public function test_elevate_session_is_idempotent_for_the_same_request(): void
    {
        $owner = $this->onboardOwner($this, 'elevate-idempotent@test.local');
        $key = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $key,
        ];

        $first = $this->postJson('/api/auth/session/elevate', ['password' => 'password123'], $headers)->assertOk()->json();
        $second = $this->postJson('/api/auth/session/elevate', ['password' => 'password123'], $headers)->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'identity.session_elevated')->count());
    }
}
