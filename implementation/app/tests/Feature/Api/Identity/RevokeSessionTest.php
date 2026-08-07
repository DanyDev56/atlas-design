<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Atlas\Modules\Identity\Application\RevokeSessionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class RevokeSessionTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_revoked_session_cannot_access_protected_routes(): void
    {
        $owner = $this->onboardOwner($this);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/dashboard", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $this->postJson('/api/auth/session/revoke', [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJson([
                'status' => 'Revoked',
            ]);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/dashboard", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertUnauthorized();

        $this->assertDatabaseHas('identity.sessions', [
            'user_id' => $owner['user_id'],
            'status' => 'Revoked',
        ]);

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'identity.session_revoked',
        ]);
    }

    public function test_revoke_session_handler_is_idempotent(): void
    {
        $owner = $this->onboardOwner($this, 'revoke-idempotent@crm.test');

        $session = DB::table('identity.sessions')
            ->where('user_id', $owner['user_id'])
            ->where('status', 'Active')
            ->first();

        $this->assertNotNull($session);

        $handler = app(RevokeSessionHandler::class);
        $requestId = (string) Str::uuid();

        $first = $handler->handle($owner['user_id'], (string) $session->id, $requestId);
        $second = $handler->handle($owner['user_id'], (string) $session->id, $requestId);

        $this->assertSame($first, $second);

        $this->assertSame(
            1,
            DB::table('platform.outbox_messages')
                ->where('event_type', 'identity.session_revoked')
                ->where('payload->session_id', $session->id)
                ->count(),
        );
    }
}
