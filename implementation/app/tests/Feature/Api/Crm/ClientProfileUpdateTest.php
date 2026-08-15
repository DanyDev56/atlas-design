<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class ClientProfileUpdateTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_updates_active_client_profile_idempotently_without_losing_metadata(): void
    {
        $owner = $this->onboardOwner($this);
        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Studio Initial',
            'profile' => [
                'email' => 'initial@studio.test',
                'demo_scenario_version' => 3,
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
        $clientId = $client->json('client_id');

        $payload = [
            'changes' => [
                'display_name' => 'Studio Nouvelle Vague',
                'legal_name' => 'Studio Nouvelle Vague SAS',
                'description' => 'Partenaire pour les projets éditoriaux.',
                'email' => 'bonjour@nouvelle-vague.test',
                'phone' => '+33 1 84 80 20 26',
                'website' => 'https://nouvelle-vague.test',
                'postal_address' => [
                    'line1' => '12 rue des Fleurs',
                    'postal_code' => '75011',
                    'city' => 'Paris',
                    'country_code' => 'fr',
                ],
            ],
            'expected_revision' => 1,
        ];
        $idempotencyKey = (string) Str::uuid();

        $updated = $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/profile",
            $payload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $idempotencyKey,
            ],
        )->assertOk()
            ->assertJsonPath('display_name', 'Studio Nouvelle Vague')
            ->assertJsonPath('profile.display_name', 'Studio Nouvelle Vague')
            ->assertJsonPath('profile.postal_address.country_code', 'FR')
            ->assertJsonPath('profile.demo_scenario_version', 3)
            ->assertJsonPath('profile_version', 2)
            ->assertJsonPath('version', 2);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/profile",
            $payload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $idempotencyKey,
            ],
        )->assertOk()
            ->assertExactJson($updated->json());

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/profile",
            [
                'changes' => $payload['changes'],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client profile unchanged.');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('display_name', 'Studio Nouvelle Vague')
            ->assertJsonPath('profile.email', 'bonjour@nouvelle-vague.test')
            ->assertJsonPath('profile.demo_scenario_version', 3)
            ->assertJsonPath('profile_version', 2)
            ->assertJsonPath('version', 2);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive",
            [
                'reason' => 'Contrôle du cycle de vie',
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('version', 3);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/profile",
            [
                'changes' => ['display_name' => 'Modification interdite'],
                'expected_revision' => 3,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client is not active.');

        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.client_profile_updated')
            ->count());
        $this->assertSame(
            ['version', 'client_id', 'workspace_id', 'profile_version'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.client_profile_updated')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
    }
}
