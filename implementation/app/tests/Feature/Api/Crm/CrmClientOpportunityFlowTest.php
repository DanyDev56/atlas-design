<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class CrmClientOpportunityFlowTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_creates_client_contact_opportunity_and_qualifies(): void
    {
        $owner = $this->onboardOwner($this);

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Acme Corp',
            'profile' => ['email' => 'contact@acme.test'],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $contact = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'profile' => ['display_name' => 'Jane Doe', 'email' => 'jane@acme.test'],
            'make_primary' => true,
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.contact_id', $contact->json('contact_id'))
            ->assertJsonPath('0.profile.display_name', 'Jane Doe')
            ->assertJsonPath('0.profile.email', 'jane@acme.test')
            ->assertJsonPath('0.is_primary', true)
            ->assertJsonPath('0.status', 'Active');

        $alternateContact = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'profile' => ['display_name' => 'John Smith', 'email' => 'john@acme.test'],
            'make_primary' => false,
            'expected_revision' => 2,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $changePrimaryKey = (string) Str::uuid();
        $primaryChanged = $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/primary-contact",
            [
                'contact_id' => $alternateContact->json('contact_id'),
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $changePrimaryKey,
            ],
        )->assertOk()
            ->assertJsonPath('primary_contact_id', $alternateContact->json('contact_id'))
            ->assertJsonPath('version', 3);

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/primary-contact",
            [
                'contact_id' => $alternateContact->json('contact_id'),
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $changePrimaryKey,
            ],
        )->assertOk()
            ->assertExactJson($primaryChanged->json());

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/primary-contact",
            ['contact_id' => null, 'expected_revision' => 3],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('primary_contact_id', null)
            ->assertJsonPath('version', 4);

        $updateContactKey = (string) Str::uuid();
        $contactUpdated = $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$contact->json('contact_id')}",
            [
                'profile' => [
                    'display_name' => 'Jane Martin',
                    'email' => 'jane.martin@acme.test',
                    'phone' => '+33 6 12 34 56 78',
                    'role' => 'Directrice de projet',
                ],
                'expected_revision' => 4,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $updateContactKey,
            ],
        )->assertOk()
            ->assertJsonPath('contact_version', 2)
            ->assertJsonPath('client_version', 5);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$contact->json('contact_id')}",
            [
                'profile' => [
                    'display_name' => 'Jane Martin',
                    'email' => 'jane.martin@acme.test',
                    'phone' => '+33 6 12 34 56 78',
                    'role' => 'Directrice de projet',
                ],
                'expected_revision' => 4,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $updateContactKey,
            ],
        )->assertOk()
            ->assertExactJson($contactUpdated->json());

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('0.profile.display_name', 'Jane Martin')
            ->assertJsonPath('0.profile.email', 'jane.martin@acme.test')
            ->assertJsonPath('0.profile.phone', '+33 6 12 34 56 78')
            ->assertJsonPath('0.profile.role', 'Directrice de projet')
            ->assertJsonPath('0.version', 2);

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'contact_id' => $contact->json('contact_id'),
            'title' => 'Refonte site web',
            'estimated_amount_cents' => 120000,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('status', 'Open');

        $opportunityId = $opportunity->json('opportunity_id');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('contact_id', $contact->json('contact_id'));

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'Qualified');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/pipeline", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('counts_by_status.Qualified', 1);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-context", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('current_display_name', 'Acme Corp');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/commercial-context", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('opportunity_status', 'Qualified')
            ->assertJsonPath('contact_id', $contact->json('contact_id'));

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'crm.client_created')
                ->exists()
        );
        $this->assertSame(3, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.client_primary_contact_changed')
            ->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.contact_updated')
            ->count());
    }
}
