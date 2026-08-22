<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Workspace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\ElevatesSession;

final class WorkspaceSettingsTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;
    use ElevatesSession;

    public function test_owner_reads_and_updates_profile_and_preferences(): void
    {
        $owner = $this->onboardOwner($this, 'settings-owner@test.local');
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
        ];

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/profile", $headers)
            ->assertOk()
            ->assertJsonPath('display_name', 'CRM Workspace')
            ->assertJsonPath('profile_version', 1);

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/profile", [
            'display_name' => 'Atelier Noroît',
            'trading_name' => 'Noroît Studio',
            'activity_description' => 'Conseil indépendant',
            'expected_revision' => 1,
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('display_name', 'Atelier Noroît')
            ->assertJsonPath('trading_name', 'Noroît Studio')
            ->assertJsonPath('profile_version', 2);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/summary", $headers)
            ->assertOk()
            ->assertJsonPath('display_name', 'Atelier Noroît');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/preferences", $headers)
            ->assertOk()
            ->assertJsonPath('locale', 'fr-FR')
            ->assertJsonPath('default_currency', 'EUR');

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/preferences", [
            'locale' => 'en-GB',
            'timezone' => 'UTC',
            'default_currency' => 'EUR',
            'establishment_country' => 'BE',
            'expected_revision' => 1,
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('locale', 'en-GB')
            ->assertJsonPath('establishment_country', 'BE')
            ->assertJsonPath('preferences_version', 2);

        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'workspace.workspace_profile_updated')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'workspace.workspace_preferences_changed')->count());
    }

    public function test_billing_identity_requires_step_up_then_succeeds(): void
    {
        $owner = $this->onboardOwner($this, 'settings-billing@test.local');
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
        ];

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/billing-identity", [
            'legal_name' => 'Atelier Noroît SAS',
            'administrative_email' => 'facturation@noroit.test',
            'expected_revision' => 1,
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertForbidden()
            ->assertJsonPath('error', 'StepUpRequired');

        $this->elevateSession($this, $owner);

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/billing-identity", [
            'legal_name' => 'Atelier Noroît SAS',
            'administrative_email' => 'facturation@noroit.test',
            'expected_revision' => 1,
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('legal_name', 'Atelier Noroît SAS')
            ->assertJsonPath('billing_identity_version', 2);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/members", $headers)
            ->assertOk()
            ->assertJsonPath('members.0.email', 'settings-billing@test.local')
            ->assertJsonPath('members.0.role', 'owner');
    }

    public function test_member_cannot_update_profile(): void
    {
        $owner = $this->onboardOwner($this, 'settings-owner-authz@test.local');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'settings-member@test.local');

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/profile", [
            'display_name' => 'Intrusion',
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$member['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertForbidden();
    }

    public function test_profile_update_is_idempotent(): void
    {
        $owner = $this->onboardOwner($this, 'settings-idempotent@test.local');
        $key = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $key,
        ];
        $payload = [
            'display_name' => 'Studio Replay',
            'trading_name' => null,
            'activity_description' => null,
            'expected_revision' => 1,
        ];

        $first = $this->patchJson("/api/workspaces/{$owner['workspace_id']}/profile", $payload, $headers)->assertOk()->json();
        $second = $this->patchJson("/api/workspaces/{$owner['workspace_id']}/profile", $payload, $headers)->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'workspace.workspace_profile_updated')->count());
    }
}
