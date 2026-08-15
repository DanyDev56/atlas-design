<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class ClientBillingProfileUpdateTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_replaces_active_client_billing_profile_idempotently(): void
    {
        $owner = $this->onboardOwner($this);
        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Atelier Fiscal',
            'billing_profile' => [
                'billing_name' => 'Atelier Fiscal SARL',
                'billing_email' => 'ancien@atelier-fiscal.test',
                'registration_identifiers' => [
                    ['type' => 'SIRET', 'value' => '111 222 333 00044'],
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
        $clientId = $client->json('client_id');

        $payload = [
            'billing_profile' => [
                'billing_name' => 'Atelier Fiscal SAS',
                'billing_email' => 'factures@atelier-fiscal.test',
                'billing_address' => [
                    'line1' => '8 avenue de la République',
                    'postal_code' => '69002',
                    'city' => 'Lyon',
                    'country_code' => 'fr',
                ],
                'registration_identifiers' => [
                    ['type' => 'siret', 'value' => '999 888 777 00066'],
                    ['type' => 'RCS', 'value' => 'Lyon B 999 888 777'],
                ],
                'tax_identifiers' => [
                    ['type' => 'vat', 'value' => 'FR12999888777'],
                ],
            ],
            'expected_revision' => 1,
        ];
        $idempotencyKey = (string) Str::uuid();

        $updated = $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            $payload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $idempotencyKey,
            ],
        )->assertOk()
            ->assertJsonPath('billing_profile.billing_name', 'Atelier Fiscal SAS')
            ->assertJsonPath('billing_profile.billing_address.country_code', 'FR')
            ->assertJsonPath('billing_profile.registration_identifiers.0.type', 'SIRET')
            ->assertJsonPath('billing_profile.tax_identifiers.0.type', 'VAT')
            ->assertJsonPath('billing_profile_version', 2)
            ->assertJsonPath('version', 2);

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            $payload,
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $idempotencyKey,
            ],
        )->assertOk()
            ->assertExactJson($updated->json());

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            [
                'billing_profile' => $payload['billing_profile'],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client billing profile unchanged.');

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            [
                'billing_profile' => [
                    'registration_identifiers' => [
                        ['type' => 'SIRET', 'value' => '111'],
                        ['type' => 'siret', 'value' => '222'],
                    ],
                ],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client billing identifiers invalid.');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('billing_profile.billing_email', 'factures@atelier-fiscal.test')
            ->assertJsonPath('billing_profile_version', 2)
            ->assertJsonPath('version', 2);

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            [
                'billing_profile' => [],
                'expected_revision' => 2,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('billing_profile', [])
            ->assertJsonPath('billing_profile_version', 3)
            ->assertJsonPath('version', 3);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/archive",
            ['reason' => 'Fin du test administratif', 'expected_revision' => 3],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk();

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$clientId}/billing-profile",
            [
                'billing_profile' => ['billing_name' => 'Modification interdite'],
                'expected_revision' => 4,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Client is not active.');

        $this->assertSame(2, DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.client_billing_profile_updated')
            ->count());
        $this->assertSame(
            ['version', 'client_id', 'workspace_id', 'billing_profile_version'],
            array_keys(json_decode(
                (string) DB::table('platform.outbox_messages')
                    ->where('event_type', 'crm.client_billing_profile_updated')
                    ->value('payload'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )),
        );
    }
}
