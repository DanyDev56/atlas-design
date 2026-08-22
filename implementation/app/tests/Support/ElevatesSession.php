<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

trait ElevatesSession
{
    /** @param array{token: string} $owner */
    protected function elevateSession(IntegrationTestCase $test, array $owner, string $password = 'password123'): void
    {
        $test->postJson('/api/auth/session/elevate', [
            'password' => $password,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('elevation_scope', 'PermissionScoped');
    }
}
