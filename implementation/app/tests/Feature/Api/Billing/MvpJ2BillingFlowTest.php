<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class MvpJ2BillingFlowTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_runs_quote_accept_invoice_payment_flow(): void
    {
        $owner = $this->onboardOwner($this, 'billing@mvp.test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Billing Client',
            'profile' => ['email' => 'billing@client.test'],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Projet facturation',
            'estimated_amount_cents' => 50000,
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
            'client_id' => $clientId,
            'opportunity_id' => $opportunityId,
            'currency' => 'EUR',
            'lines' => [
                [
                    'description' => 'Prestation',
                    'quantity' => 1,
                    'unit_price_cents' => 50000,
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('status', 'Draft')
            ->assertJsonPath('total_cents', 50000);

        $quoteId = $quote->json('quote_id');

        $this->patchJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}", [
            'expected_revision' => 1,
            'lines' => [
                [
                    'description' => 'Prestation finale',
                    'quantity' => 1,
                    'unit_price_cents' => 50000,
                ],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('version', 2);

        $sent = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 2,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'Sent');

        $acceptToken = $sent->json('public_accept_token');

        $this->postJson("/api/public/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/accept", [
            'public_token' => $acceptToken,
            'expected_revision' => 3,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'Accepted');

        app(OutboxProcessor::class)->processPending();

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('status', 'Won');

        $invoice = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('status', 'Draft')
            ->assertJsonPath('balance_cents', 50000);

        $invoiceId = $invoice->json('invoice_id');

        $issued = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/issue", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'Issued');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/send", [
            'expected_revision' => $issued->json('version'),
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/payments", [
            'amount_cents' => 50000,
            'reference' => 'VIR-001',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('balance_cents', 0)
            ->assertJsonPath('settlement_status', 'Paid');

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'billing.quote_accepted')
                ->where('payload->quote_id', $quoteId)
                ->exists()
        );

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'crm.opportunity_won')
                ->where('payload->quote_id', $quoteId)
                ->exists()
        );
    }
}
