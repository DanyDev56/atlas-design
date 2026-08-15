<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class ClientActivityTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_records_and_reads_past_client_activities_idempotently(): void
    {
        $owner = $this->onboardOwner($this);
        $headers = ['Authorization' => 'Bearer '.$owner['token']];
        $client = $this->createClient($owner, 'Atelier Chronologie');
        $clientId = $client['client_id'];

        $contact = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'profile' => ['display_name' => 'Camille Martin'],
            'make_primary' => false,
            'expected_revision' => 1,
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $contactId = $contact->json('contact_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'contact_id' => $contactId,
            'title' => 'Nouvelle identité',
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $opportunityId = $opportunity->json('opportunity_id');

        $payload = [
            'contact_id' => $contactId,
            'opportunity_id' => $opportunityId,
            'kind' => 'Meeting',
            'summary' => 'Atelier de cadrage avec validation des objectifs.',
            'occurred_at' => now()->subHours(2)->toIso8601String(),
        ];
        $idempotencyKey = (string) Str::uuid();
        $recorded = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertCreated()
            ->assertJsonPath('kind', 'Meeting')
            ->assertJsonPath('summary', $payload['summary'])
            ->assertJsonPath('contact_id', $contactId)
            ->assertJsonPath('opportunity_id', $opportunityId)
            ->assertJsonPath('status', 'Recorded')
            ->assertJsonPath('version', 1);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertCreated()->assertExactJson($recorded->json());

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities", [
            'kind' => 'Call',
            'summary' => 'Appel de suivi après la réunion.',
            'occurred_at' => now()->subHour()->toIso8601String(),
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities",
            $headers,
        )->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.kind', 'Call')
            ->assertJsonPath('1.kind', 'Meeting');

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities/audit",
            $headers,
        )->assertOk()->assertJsonCount(0);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}", $headers)
            ->assertOk()
            ->assertJsonPath('version', 1);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities", [
            'kind' => 'Note',
            'summary' => 'Cette note ne doit pas être acceptée.',
            'occurred_at' => now()->addHour()->toIso8601String(),
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('messages.0', 'Activity occurred at is in the future.');

        $foreignClient = $this->createClient($owner, 'Autre dossier');
        $foreignContact = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$foreignClient['client_id']}/contacts",
            [
                'profile' => ['display_name' => 'Contact externe'],
                'make_primary' => false,
                'expected_revision' => 1,
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertCreated();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities", [
            'contact_id' => $foreignContact->json('contact_id'),
            'kind' => 'Email',
            'summary' => 'Référence incompatible.',
            'occurred_at' => now()->subMinute()->toIso8601String(),
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('messages.0', 'Activity contact reference conflict.');

        $foreignOpportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $foreignClient['client_id'],
            'title' => 'Opportunité étrangère',
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/activities", [
            'opportunity_id' => $foreignOpportunity->json('opportunity_id'),
            'kind' => 'Note',
            'summary' => 'Cette opportunité appartient à un autre dossier.',
            'occurred_at' => now()->subMinute()->toIso8601String(),
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonPath('messages.0', 'Activity opportunity reference conflict.');

        $archivedClient = $this->createClient($owner, 'Dossier archivé');
        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$archivedClient['client_id']}/activities",
            [
                'kind' => 'Note',
                'summary' => 'Historique conservé avant archivage.',
                'occurred_at' => now()->subDay()->toIso8601String(),
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertCreated();
        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$archivedClient['client_id']}/archive",
            ['reason' => 'Test chronologie', 'expected_revision' => 1],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertOk();
        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$archivedClient['client_id']}/activities",
            $headers,
        )->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.summary', 'Historique conservé avant archivage.');
        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$archivedClient['client_id']}/activities",
            [
                'kind' => 'Note',
                'summary' => 'Ajout interdit après archivage.',
                'occurred_at' => now()->subMinute()->toIso8601String(),
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client not found.');

        $events = DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.activity_recorded')
            ->orderBy('occurred_at')
            ->get();
        $this->assertCount(3, $events);
        $eventPayload = json_decode($events->first()->payload, true, 512, JSON_THROW_ON_ERROR);
        $expectedEventKeys = ['activity_id', 'workspace_id', 'client_id', 'kind', 'activity_occurred_at', 'version'];
        $actualEventKeys = array_keys($eventPayload);
        sort($expectedEventKeys);
        sort($actualEventKeys);
        $this->assertSame($expectedEventKeys, $actualEventKeys);
        $this->assertArrayNotHasKey('summary', $eventPayload);
    }

    public function test_owner_corrects_activity_while_preserving_previous_revision(): void
    {
        $owner = $this->onboardOwner($this);
        $headers = ['Authorization' => 'Bearer '.$owner['token']];
        $client = $this->createClient($owner, 'Atelier Correction');
        $originalOccurredAt = now()->subDays(2)->toIso8601String();
        $recorded = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities",
            [
                'kind' => 'Call',
                'summary' => 'Appel initial saisi avec une information erronée.',
                'occurred_at' => $originalOccurredAt,
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertCreated();
        $activityId = $recorded->json('activity_id');
        $payload = [
            'content' => [
                'kind' => 'Meeting',
                'summary' => 'Réunion de cadrage confirmée avec le client.',
                'occurred_at' => now()->subDay()->toIso8601String(),
            ],
            'correction_reason' => 'Le compte-rendu initial indiquait le mauvais canal.',
            'expected_revision' => 1,
        ];
        $idempotencyKey = (string) Str::uuid();

        $corrected = $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()
            ->assertJsonPath('activity_id', $activityId)
            ->assertJsonPath('kind', 'Meeting')
            ->assertJsonPath('summary', $payload['content']['summary'])
            ->assertJsonPath('version', 2);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()->assertExactJson($corrected->json());

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}",
            $payload,
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Revision conflict.');

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities",
            $headers,
        )->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.summary', $payload['content']['summary'])
            ->assertJsonPath('0.version', 2);

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities/audit",
            $headers,
        )->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'Recorded')
            ->assertJsonPath('0.aggregate_version', 2)
            ->assertJsonPath('0.current_content.revision', 2)
            ->assertJsonPath('0.current_content.summary', $payload['content']['summary'])
            ->assertJsonPath('0.corrections.0.revision', 1)
            ->assertJsonPath('0.corrections.0.reason', $payload['correction_reason'])
            ->assertJsonPath('0.corrections.0.actor_user_id', $owner['user_id'])
            ->assertJsonPath('0.removal', null);

        $revision = DB::table('crm.activity_revisions')
            ->where('activity_id', $activityId)
            ->first();
        $this->assertNotNull($revision);
        $this->assertSame(1, (int) $revision->revision);
        $this->assertSame('Call', $revision->kind);
        $this->assertSame('Appel initial saisi avec une information erronée.', $revision->summary);
        $this->assertSame($payload['correction_reason'], $revision->correction_reason);
        $this->assertSame($owner['user_id'], $revision->corrected_by);

        $event = DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.activity_corrected')
            ->sole();
        $eventPayload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $eventPayload['version']);
        $this->assertArrayNotHasKey('summary', $eventPayload);
        $this->assertArrayNotHasKey('correction_reason', $eventPayload);
    }

    public function test_owner_removes_activity_terminally_without_deleting_its_audit(): void
    {
        $owner = $this->onboardOwner($this);
        $headers = ['Authorization' => 'Bearer '.$owner['token']];
        $client = $this->createClient($owner, 'Atelier Retrait');
        $recorded = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities",
            [
                'kind' => 'Call',
                'summary' => 'Appel attribué par erreur à ce dossier.',
                'occurred_at' => now()->subDays(2)->toIso8601String(),
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertCreated();
        $activityId = $recorded->json('activity_id');

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}",
            [
                'content' => [
                    'kind' => 'Call',
                    'summary' => 'Appel attribué au mauvais dossier client.',
                    'occurred_at' => now()->subDays(2)->toIso8601String(),
                ],
                'correction_reason' => 'Précision apportée avant le retrait définitif.',
                'expected_revision' => 1,
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertOk()->assertJsonPath('version', 2);

        $payload = [
            'removal_reason' => 'Cette activité concerne un autre client.',
            'expected_revision' => 2,
        ];
        $idempotencyKey = (string) Str::uuid();
        $removed = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}/remove",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()
            ->assertJsonPath('activity_id', $activityId)
            ->assertJsonPath('status', 'Removed')
            ->assertJsonPath('version', 3)
            ->assertJsonStructure(['removed_at']);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}/remove",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()->assertExactJson($removed->json());

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities",
            $headers,
        )->assertOk()->assertJsonCount(0);

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client['client_id']}/activities/audit",
            $headers,
        )->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'Removed')
            ->assertJsonPath('0.aggregate_version', 3)
            ->assertJsonPath('0.current_content.revision', 2)
            ->assertJsonPath('0.corrections.0.revision', 1)
            ->assertJsonPath('0.removal.reason', $payload['removal_reason'])
            ->assertJsonPath('0.removal.actor_user_id', $owner['user_id']);

        $activity = DB::table('crm.activities')->where('id', $activityId)->sole();
        $this->assertSame('Removed', $activity->status);
        $this->assertSame($payload['removal_reason'], $activity->removal_reason);
        $this->assertSame($owner['user_id'], $activity->removed_by);
        $this->assertNotNull($activity->removed_at);
        $this->assertSame(1, DB::table('crm.activity_revisions')->where('activity_id', $activityId)->count());

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}/remove",
            ['removal_reason' => 'Nouvelle demande.', 'expected_revision' => 3],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Activity is not recorded.');

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/activities/{$activityId}",
            [
                'content' => [
                    'kind' => 'Meeting',
                    'summary' => 'Une activité retirée ne peut plus être corrigée.',
                    'occurred_at' => now()->subDay()->toIso8601String(),
                ],
                'correction_reason' => 'Tentative interdite après retrait.',
                'expected_revision' => 3,
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Activity is not recorded.');

        $event = DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.activity_removed')
            ->sole();
        $eventPayload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(3, $eventPayload['version']);
        $this->assertArrayNotHasKey('summary', $eventPayload);
        $this->assertArrayNotHasKey('removal_reason', $eventPayload);
    }

    /** @param array{workspace_id: string, token: string} $owner */
    /** @return array{client_id: string} */
    private function createClient(array $owner, string $displayName): array
    {
        return $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => $displayName,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()->json();
    }
}
