<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class DepositInvoiceFlowTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_deposit_then_issued_remainder_final_invoice(): void
    {
        $owner = $this->onboardOwner($this, 'deposit-invoice@test.local');
        $quoteId = $this->acceptedQuote($owner, 50000);
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        $deposit = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/deposit-invoices", [
            'amount_cents' => 15000,
            'expected_revision' => 3,
        ], $headers)->assertCreated()
            ->assertJsonPath('kind', 'Deposit')
            ->assertJsonPath('total_cents', 15000)
            ->assertJsonPath('status', 'Draft');

        $replay = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/deposit-invoices", [
            'amount_cents' => 15000,
            'expected_revision' => 3,
        ], $headers)->assertCreated()->json();
        $this->assertEquals($deposit->json(), $replay);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Deposit invoice is still a draft.');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$deposit->json('invoice_id')}/issue", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $final = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('kind', 'Final')
            ->assertJsonPath('total_cents', 35000);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('deposit_invoice_id', $deposit->json('invoice_id'))
            ->assertJsonPath('final_invoice_id', $final->json('invoice_id'));
    }

    public function test_deposit_cannot_exceed_quote_or_follow_a_final_invoice(): void
    {
        $owner = $this->onboardOwner($this, 'deposit-invoice-cap@test.local');
        $quoteId = $this->acceptedQuote($owner, 20000);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/deposit-invoices", [
            'amount_cents' => 25000,
            'expected_revision' => 3,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Invoice amount exceeds quote total.');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/deposit-invoices", [
            'amount_cents' => 5000,
            'expected_revision' => 3,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Final invoice already exists.');
    }

    private function acceptedQuote(array $owner, int $amountCents): string
    {
        $flow = $this->prepareAcceptedQuote($owner, $amountCents);

        return $flow['quote_id'];
    }

    /** @return array{quote_id: string} */
    private function prepareAcceptedQuote(array $owner, int $amountCents): array
    {
        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Deposit Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $client->json('client_id'),
            'title' => 'Deposit Opp',
            'estimated_amount_cents' => $amountCents,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunity->json('opportunity_id')}/qualify", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $quote = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes", [
            'client_id' => $client->json('client_id'),
            'opportunity_id' => $opportunity->json('opportunity_id'),
            'currency' => 'EUR',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $amountCents],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $quoteId = $quote->json('quote_id');
        $sent = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/public/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/accept", [
            'public_token' => $sent->json('public_accept_token'),
            'expected_revision' => 2,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        return ['quote_id' => $quoteId];
    }
}
