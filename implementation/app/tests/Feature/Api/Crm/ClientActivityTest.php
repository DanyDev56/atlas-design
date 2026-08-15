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
