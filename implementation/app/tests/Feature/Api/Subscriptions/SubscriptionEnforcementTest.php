<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Subscriptions;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class SubscriptionEnforcementTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_enforcement_disabled_never_blocks_existing_mutations(): void
    {
        config()->set('subscriptions.enforcement_enabled', false);
        $owner = $this->onboardOwner($this, 'enforcement-disabled@test.local');

        $this->createClient($owner)->assertCreated();
    }

    public function test_expired_access_blocks_mutations_but_preserves_reads(): void
    {
        config()->set('subscriptions.enforcement_enabled', true);
        config()->set('subscriptions.past_due_grace_days', 7);
        $owner = $this->onboardOwner($this, 'enforcement-expired@test.local');
        app(OutboxProcessor::class)->processPending();

        $this->createClient($owner, 'Client actif')->assertCreated();
        DB::table('subscriptions.entitlements')
            ->where('workspace_id', $owner['workspace_id'])
            ->update(['valid_until' => now('UTC')->subSecond()]);

        $this->createClient($owner, 'Client bloqué')->assertStatus(402)
            ->assertJsonPath('error', 'SubscriptionAccessRestricted')
            ->assertJsonPath('capability', 'workspace.mutate')
            ->assertJsonPath('access_level', 'Restricted');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()->assertJsonCount(1);
    }

    public function test_foreign_workspace_is_hidden_before_its_commercial_state_is_exposed(): void
    {
        config()->set('subscriptions.enforcement_enabled', true);
        config()->set('subscriptions.past_due_grace_days', 7);
        $first = $this->onboardOwner($this, 'enforcement-first@test.local');
        $second = $this->onboardOwner($this, 'enforcement-second@test.local');
        app(OutboxProcessor::class)->processPending();
        DB::table('subscriptions.entitlements')
            ->where('workspace_id', $second['workspace_id'])
            ->update(['valid_until' => now('UTC')->subSecond()]);

        $this->postJson("/api/workspaces/{$second['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Tentative étrangère',
        ], [
            'Authorization' => 'Bearer '.$first['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertForbidden()
            ->assertJsonPath('error', 'Forbidden');
    }

    public function test_enforcement_fails_closed_when_grace_policy_is_not_configured(): void
    {
        config()->set('subscriptions.enforcement_enabled', true);
        config()->set('subscriptions.past_due_grace_days', null);
        $owner = $this->onboardOwner($this, 'enforcement-policy@test.local');
        app(OutboxProcessor::class)->processPending();

        $this->createClient($owner)->assertServiceUnavailable()
            ->assertJsonPath('error', 'SubscriptionPolicyUnavailable');
    }

    public function test_pending_invitation_cannot_bypass_restricted_member_capability(): void
    {
        config()->set('subscriptions.enforcement_enabled', true);
        config()->set('subscriptions.past_due_grace_days', 7);
        $owner = $this->onboardOwner($this, 'enforcement-invite-owner@test.local');
        app(OutboxProcessor::class)->processPending();
        $invitee = $this->verifiedUser('enforcement-invitee@test.local');

        $invitation = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invitations", [
            'email' => 'enforcement-invitee@test.local',
            'debug_invitation_token' => true,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
        DB::table('subscriptions.entitlements')
            ->where('workspace_id', $owner['workspace_id'])
            ->update(['valid_until' => now('UTC')->subSecond()]);

        $this->postJson("/api/invitations/{$invitation->json('invitation_id')}/accept", [
            'token' => $invitation->json('invitation_token'),
        ], [
            'Authorization' => 'Bearer '.$invitee['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(402)
            ->assertJsonPath('error', 'SubscriptionAccessRestricted');

        self::assertSame(0, DB::table('identity.memberships')
            ->where('workspace_id', $owner['workspace_id'])
            ->where('user_id', $invitee['user_id'])
            ->count());
    }

    public function test_pending_invitations_reserve_the_configured_member_slots(): void
    {
        config()->set('subscriptions.enforcement_enabled', true);
        config()->set('subscriptions.past_due_grace_days', 7);
        $owner = $this->onboardOwner($this, 'enforcement-limit-owner@test.local');
        app(OutboxProcessor::class)->processPending();

        foreach (['first-slot@test.local', 'second-slot@test.local'] as $email) {
            $this->postJson("/api/workspaces/{$owner['workspace_id']}/invitations", [
                'email' => $email,
            ], [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ])->assertCreated();
        }

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invitations", [
            'email' => 'over-limit@test.local',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertConflict()
            ->assertJsonPath('error', 'SubscriptionLimitExceeded')
            ->assertJsonPath('messages.0', 'Workspace subscription limit reached.')
            ->assertJsonPath('limit_name', 'members_total')
            ->assertJsonPath('limit', 3)
            ->assertJsonPath('current', 3);

        self::assertSame(2, DB::table('identity.invitations')
            ->where('workspace_id', $owner['workspace_id'])
            ->count());
    }

    /**
     * @param  array{token: string, user_id: string, workspace_id: string}  $owner
     */
    private function createClient(array $owner, string $name = 'Client test'): TestResponse
    {
        return $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => $name,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
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

        return ['token' => $login->json('token'), 'user_id' => $login->json('user_id')];
    }
}
