<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class WorkspaceInvitationTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;

    public function test_owner_invites_verified_user_who_accepts_atomically(): void
    {
        $owner = $this->onboardOwner($this, 'invite-owner@test.local');
        $invitee = $this->verifiedUser('invitee@test.local');
        $requestId = (string) Str::uuid();

        $invitation = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            [
                'email' => 'Invitee@Test.Local',
                'debug_invitation_token' => true,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $requestId,
            ],
        )->assertCreated()
            ->assertJsonPath('recipient_email', 'invitee@test.local')
            ->assertJsonPath('role', 'member')
            ->assertJsonPath('status', 'Pending');

        $invitationId = $invitation->json('invitation_id');
        $token = $invitation->json('invitation_token');
        $this->assertIsString($token);

        $replay = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            ['email' => 'invitee@test.local', 'debug_invitation_token' => true],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $requestId,
            ],
        )->assertCreated();
        $this->assertSame($invitationId, $replay->json('invitation_id'));
        $replay->assertJsonMissingPath('invitation_token');

        $acceptRequestId = (string) Str::uuid();
        $accepted = $this->postJson(
            "/api/invitations/{$invitationId}/accept",
            ['token' => $token],
            [
                'Authorization' => 'Bearer '.$invitee['token'],
                'Idempotency-Key' => $acceptRequestId,
            ],
        )->assertOk()
            ->assertJsonPath('workspace_id', $owner['workspace_id'])
            ->assertJsonPath('status', 'Accepted');

        $this->assertDatabaseHas('identity.memberships', [
            'id' => $accepted->json('membership_id'),
            'user_id' => $invitee['user_id'],
            'workspace_id' => $owner['workspace_id'],
            'status' => 'Active',
        ]);
        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'identity.invitation_accepted',
        ]);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/members", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonFragment(['email' => 'invitee@test.local', 'role' => 'member']);

        $this->postJson(
            "/api/invitations/{$invitationId}/accept",
            ['token' => $token],
            [
                'Authorization' => 'Bearer '.$invitee['token'],
                'Idempotency-Key' => $acceptRequestId,
            ],
        )->assertOk()
            ->assertJsonPath('membership_id', $accepted->json('membership_id'));

        $this->postJson(
            "/api/invitations/{$invitationId}/accept",
            ['token' => $token],
            [
                'Authorization' => 'Bearer '.$invitee['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Invitation proof invalid.');
    }

    public function test_invitation_rejects_wrong_recipient_and_member_cannot_invite(): void
    {
        $owner = $this->onboardOwner($this, 'invite-security-owner@test.local');
        $member = $this->addMemberToWorkspace(
            $this,
            $owner['workspace_id'],
            'invite-security-member@test.local',
        );
        $other = $this->verifiedUser('other-invitee@test.local');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            ['email' => 'blocked@test.local'],
            [
                'Authorization' => 'Bearer '.$member['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertForbidden();

        $invitation = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            ['email' => 'expected-invitee@test.local', 'debug_invitation_token' => true],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertCreated();

        $this->postJson(
            "/api/invitations/{$invitation->json('invitation_id')}/accept",
            ['token' => $invitation->json('invitation_token')],
            [
                'Authorization' => 'Bearer '.$other['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Invitation proof invalid.');
    }

    public function test_invitation_token_is_hidden_when_debug_is_disabled(): void
    {
        $owner = $this->onboardOwner($this, 'invite-hidden-owner@test.local');
        config()->set('platform.development.debug_verification_tokens', false);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            ['email' => 'hidden-invitee@test.local', 'debug_invitation_token' => true],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertCreated()
            ->assertJsonMissingPath('invitation_token');
    }

    public function test_owner_revokes_pending_invitation_idempotently(): void
    {
        $owner = $this->onboardOwner($this, 'invite-revoke-owner@test.local');
        $invitee = $this->verifiedUser('invite-revoke-user@test.local');
        $invitation = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invitations",
            ['email' => 'invite-revoke-user@test.local', 'debug_invitation_token' => true],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertCreated();

        $requestId = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $requestId,
        ];
        $path = "/api/workspaces/{$owner['workspace_id']}/invitations/{$invitation->json('invitation_id')}/revoke";

        $this->postJson($path, [], $headers)
            ->assertOk()
            ->assertJsonPath('status', 'Revoked');
        $this->postJson($path, [], $headers)
            ->assertOk()
            ->assertJsonPath('status', 'Revoked');

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'identity.invitation_revoked',
        ]);

        $this->postJson(
            "/api/invitations/{$invitation->json('invitation_id')}/accept",
            ['token' => $invitation->json('invitation_token')],
            [
                'Authorization' => 'Bearer '.$invitee['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Invitation proof invalid.');
    }

    /** @return array{token: string, user_id: string} */
    private function verifiedUser(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Invited Member',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson('/api/auth/verify-email', [
            'user_id' => $register->json('user_id'),
            'token' => $register->json('verification_token'),
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();

        return [
            'token' => $login->json('token'),
            'user_id' => $login->json('user_id'),
        ];
    }
}
