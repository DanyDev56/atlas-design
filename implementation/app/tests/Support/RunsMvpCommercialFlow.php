<?php

declare(strict_types=1);

namespace Tests\Support;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

trait RunsMvpCommercialFlow
{
    /** @return array{client_id: string, quote_id: string, invoice_id: string} */
    protected function runMvpJ2ThroughPayment(IntegrationTestCase $test, array $owner, int $amountCents = 50000): array
    {
        $client = $test->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Acceptance Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $test->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Acceptance Opp',
            'estimated_amount_cents' => $amountCents,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunityId = $opportunity->json('opportunity_id');

        $test->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $quote = $test->postJson("/api/workspaces/{$owner['workspace_id']}/quotes", [
            'client_id' => $clientId,
            'opportunity_id' => $opportunityId,
            'currency' => 'EUR',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $amountCents],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $quoteId = $quote->json('quote_id');

        $sent = $test->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $test->postJson("/api/public/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/accept", [
            'public_token' => $sent->json('public_accept_token'),
            'expected_revision' => 2,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        app(OutboxProcessor::class)->processPending();

        $invoice = $test->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $invoiceId = $invoice->json('invoice_id');

        $test->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/issue", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $test->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/payments", [
            'amount_cents' => $amountCents,
            'reference' => 'VIR-ACCEPTANCE',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();

        return [
            'client_id' => $clientId,
            'quote_id' => $quoteId,
            'invoice_id' => $invoiceId,
        ];
    }

    protected function publishSnapshotAndProcessDerivedPipeline(IntegrationTestCase $test, array $owner): string
    {
        $published = $test->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $snapshotId = $published->json('analytics_snapshot_id');

        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();

        return $snapshotId;
    }
}
