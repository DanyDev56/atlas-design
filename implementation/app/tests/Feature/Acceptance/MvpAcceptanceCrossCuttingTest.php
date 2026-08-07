<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class MvpAcceptanceCrossCuttingTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_workspace_isolation_hides_foreign_resources(): void
    {
        $ownerA = $this->onboardOwner($this, 'isolation-a@test');
        $ownerB = $this->onboardOwner($this, 'isolation-b@test');

        $client = $this->postJson("/api/workspaces/{$ownerA['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Secret Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$ownerA['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $this->getJson("/api/workspaces/{$ownerB['workspace_id']}/clients/{$clientId}", [
            'Authorization' => 'Bearer '.$ownerB['token'],
        ])->assertStatus(422);
    }

    public function test_create_client_idempotency_replays_same_result(): void
    {
        $owner = $this->onboardOwner($this, 'idempotency-client@test');
        $idempotencyKey = (string) Str::uuid();

        $first = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Idempotent Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $idempotencyKey,
        ])->assertCreated();

        $clientId = $first->json('client_id');

        $retry = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Idempotent Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $idempotencyKey,
        ])->assertCreated();

        $retry->assertJsonPath('client_id', $clientId);
    }

    public function test_stale_revision_on_quote_send_is_rejected(): void
    {
        $owner = $this->onboardOwner($this, 'revision-conflict@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Revision Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $client->json('client_id'),
            'title' => 'Revision Opp',
            'estimated_amount_cents' => 10000,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunityId = $opportunity->json('opportunity_id');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $quote = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes", [
            'client_id' => $client->json('client_id'),
            'opportunity_id' => $opportunityId,
            'currency' => 'EUR',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 10000],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $quoteId = $quote->json('quote_id');

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}", [
            'expected_revision' => 1,
            'lines' => [
                ['description' => 'Service v2', 'quantity' => 1, 'unit_price_cents' => 10000],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_create_client(): void
    {
        $owner = $this->onboardOwner($this, 'authz-owner@test');
        $intruder = $this->onboardOwner($this, 'authz-intruder@test');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Forbidden',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$intruder['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertForbidden();
    }
}
