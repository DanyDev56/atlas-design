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

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$contact->json('contact_id')}/archive",
            ['reason' => 'A quitté l’entreprise', 'expected_revision' => 5],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Contact in use.');

        $updateOpportunityKey = (string) Str::uuid();
        $opportunityUpdated = $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}",
            [
                'changes' => [
                    'contact_id' => null,
                    'title' => 'Refonte du site et de l’identité',
                    'estimated_amount_cents' => 135000,
                    'currency' => 'eur',
                ],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $updateOpportunityKey,
            ],
        )->assertOk()
            ->assertJsonPath('contact_id', null)
            ->assertJsonPath('title', 'Refonte du site et de l’identité')
            ->assertJsonPath('estimated_amount_cents', 135000)
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('status', 'Qualified')
            ->assertJsonPath('version', 3);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}",
            [
                'changes' => [
                    'currency' => 'eur',
                    'estimated_amount_cents' => 135000,
                    'title' => 'Refonte du site et de l’identité',
                    'contact_id' => null,
                ],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $updateOpportunityKey,
            ],
        )->assertOk()
            ->assertExactJson($opportunityUpdated->json());

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('contact_id', null)
            ->assertJsonPath('title', 'Refonte du site et de l’identité')
            ->assertJsonPath('estimated_amount_cents', 135000)
            ->assertJsonPath('version', 3);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$contact->json('contact_id')}/archive",
            ['reason' => 'Interlocutrice remplacée', 'expected_revision' => 5],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Archived')
            ->assertJsonPath('client_version', 6);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}",
            [
                'changes' => ['contact_id' => $contact->json('contact_id')],
                'expected_revision' => 3,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Contact reference conflict.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$contact->json('contact_id')}/reactivate",
            ['expected_revision' => 6],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Active')
            ->assertJsonPath('client_version', 7);

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/primary-contact",
            [
                'contact_id' => $alternateContact->json('contact_id'),
                'expected_revision' => 7,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('version', 8);

        $archiveContactKey = (string) Str::uuid();
        $contactArchived = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$alternateContact->json('contact_id')}/archive",
            ['reason' => 'Doublon de contact', 'expected_revision' => 8],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $archiveContactKey,
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Archived')
            ->assertJsonPath('contact_version', 2)
            ->assertJsonPath('client_version', 9)
            ->assertJsonPath('primary_contact_id', null);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$alternateContact->json('contact_id')}/archive",
            ['reason' => 'Doublon de contact', 'expected_revision' => 8],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $archiveContactKey,
            ],
        )->assertOk()
            ->assertExactJson($contactArchived->json());

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('1.status', 'Archived')
            ->assertJsonPath('1.is_primary', false)
            ->assertJsonPath('1.version', 2)
            ->assertJsonPath('1.archived_at', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('crm.contacts', [
            'id' => $alternateContact->json('contact_id'),
            'archive_reason' => 'Doublon de contact',
            'status' => 'Archived',
        ]);

        $reactivateContactKey = (string) Str::uuid();
        $contactReactivated = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$alternateContact->json('contact_id')}/reactivate",
            ['expected_revision' => 9],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $reactivateContactKey,
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Active')
            ->assertJsonPath('contact_version', 3)
            ->assertJsonPath('client_version', 10)
            ->assertJsonPath('primary_contact_id', null);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$alternateContact->json('contact_id')}/reactivate",
            ['expected_revision' => 9],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $reactivateContactKey,
            ],
        )->assertOk()
            ->assertExactJson($contactReactivated->json());

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts/{$alternateContact->json('contact_id')}/reactivate",
            ['expected_revision' => 10],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Contact is not archived.');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('1.status', 'Active')
            ->assertJsonPath('1.is_primary', false)
            ->assertJsonPath('1.version', 3)
            ->assertJsonPath('1.archived_at', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('crm.contacts', [
            'id' => $alternateContact->json('contact_id'),
            'archive_reason' => 'Doublon de contact',
            'status' => 'Active',
        ]);

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
            ->assertJsonPath('contact_id', null);

        $loseOpportunityKey = (string) Str::uuid();
        $opportunityLost = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/lose",
            [
                'loss_reason_code' => 'Competitor',
                'loss_note' => 'Le client a retenu une équipe déjà référencée.',
                'expected_revision' => 3,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $loseOpportunityKey,
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Lost')
            ->assertJsonPath('version', 4)
            ->assertJsonPath('loss_reason_code', 'Competitor')
            ->assertJsonPath('lost_at', fn ($value) => is_string($value) && $value !== '');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/lose",
            [
                'loss_reason_code' => 'Competitor',
                'loss_note' => 'Le client a retenu une équipe déjà référencée.',
                'expected_revision' => 3,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $loseOpportunityKey,
            ],
        )->assertOk()
            ->assertExactJson($opportunityLost->json());

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/lose",
            [
                'loss_reason_code' => 'Budget',
                'expected_revision' => 4,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Opportunity is terminal.');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('status', 'Lost')
            ->assertJsonPath('loss_reason_code', 'Competitor')
            ->assertJsonPath('loss_note', 'Le client a retenu une équipe déjà référencée.')
            ->assertJsonPath('lost_at', fn ($value) => is_string($value) && $value !== '');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/pipeline", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('counts_by_status.Qualified', 0)
            ->assertJsonPath('counts_by_status.Lost', 1);

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'crm.client_created')
                ->exists()
        );
        $this->assertSame(5, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.client_primary_contact_changed')
            ->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.contact_updated')
            ->count());
        $this->assertSame(2, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.contact_archived')
            ->count());
        $this->assertSame(2, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.contact_reactivated')
            ->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.opportunity_updated')
            ->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.opportunity_lost')
            ->count());
        $this->assertSame(
            ['version', 'client_id', 'contact_id', 'workspace_id'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.contact_archived')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
        $this->assertSame(
            ['version', 'client_id', 'workspace_id', 'opportunity_id'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.opportunity_updated')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
        $this->assertSame(
            ['version', 'client_id', 'workspace_id', 'opportunity_id', 'loss_reason_code'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.opportunity_lost')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
        $this->assertSame(
            ['version', 'client_id', 'contact_id', 'workspace_id'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.contact_reactivated')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
    }

    public function test_owner_archives_client_only_after_all_opportunities_are_terminal(): void
    {
        $owner = $this->onboardOwner($this);
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Horizon Test',
        ], $headers)->assertCreated();
        $clientId = $client->json('client_id');

        $contact = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'profile' => ['display_name' => 'Alice Laurent'],
            'make_primary' => true,
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'contact_id' => $contact->json('contact_id'),
            'title' => 'Mission de test',
            'estimated_amount_cents' => 250000,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
        $opportunityId = $opportunity->json('opportunity_id');

        $archivePayload = [
            'reason' => 'Fin de la relation commerciale',
            'expected_revision' => 2,
        ];

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive", $archivePayload, [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(422)
            ->assertJsonPath('messages.0', 'Active opportunity exists.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify",
            ['expected_revision' => 1],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive", $archivePayload, [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(422)
            ->assertJsonPath('messages.0', 'Active opportunity exists.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/lose",
            [
                'loss_reason_code' => 'NoDecision',
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Lost');

        $archiveKey = (string) Str::uuid();
        $archived = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive",
            $archivePayload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $archiveKey,
            ],
        )->assertOk()
            ->assertJsonPath('client_id', $clientId)
            ->assertJsonPath('status', 'Archived')
            ->assertJsonPath('version', 3)
            ->assertJsonPath('archived_at', fn ($value) => is_string($value) && $value !== '');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive",
            $archivePayload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $archiveKey,
            ],
        )->assertOk()
            ->assertExactJson($archived->json());

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive",
            ['reason' => 'Autre motif', 'expected_revision' => 3],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client is not active.');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('status', 'Archived')
            ->assertJsonPath('version', 3)
            ->assertJsonPath('archived_at', fn ($value) => is_string($value) && $value !== '');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/contacts", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'Active')
            ->assertJsonPath('0.is_primary', true);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('0.status', 'Archived')
            ->assertJsonPath('0.archived_at', fn ($value) => is_string($value) && $value !== '');

        $this->assertDatabaseHas('crm.clients', [
            'id' => $clientId,
            'status' => 'Archived',
            'archive_reason' => 'Fin de la relation commerciale',
        ]);
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.client_archived')
            ->count());
        $this->assertSame(
            ['version', 'client_id', 'workspace_id'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.client_archived')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
    }
}
