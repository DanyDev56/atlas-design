<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class MembershipRevocationTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;

    public function test_removed_membership_cannot_mutate_workspace_with_valid_session(): void
    {
        $owner = $this->onboardOwner($this, 'owner-revoke@crm.test');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'member-revoke@crm.test');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Individual',
            'display_name' => 'Before removal',
        ], [
            'Authorization' => 'Bearer '.$member['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/memberships/{$member['membership_id']}/remove",
            [],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJson([
                'membership_id' => $member['membership_id'],
                'status' => 'Removed',
            ]);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Individual',
            'display_name' => 'After removal',
        ], [
            'Authorization' => 'Bearer '.$member['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertForbidden();

        $this->assertDatabaseHas('identity.memberships', [
            'id' => $member['membership_id'],
            'status' => 'Removed',
        ]);

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'identity.membership_removed',
        ]);
    }

    public function test_owner_cannot_remove_own_membership(): void
    {
        $owner = $this->onboardOwner($this, 'solo-owner@crm.test');

        $membership = DB::table('identity.memberships')
            ->where('user_id', $owner['user_id'])
            ->where('workspace_id', $owner['workspace_id'])
            ->first();

        $this->assertNotNull($membership);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/memberships/{$membership->id}/remove",
            [],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Cannot remove own membership.');
    }
}
