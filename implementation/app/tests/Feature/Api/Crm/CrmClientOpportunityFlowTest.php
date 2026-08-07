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
            ->assertJsonPath('opportunity_status', 'Qualified');

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'crm.client_created')
                ->exists()
        );
    }
}
