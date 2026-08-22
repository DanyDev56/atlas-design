<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Analytics;

use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class AnalyticsSnapshotTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_j2_flow_ingest_and_publish_snapshot(): void
    {
        $owner = $this->onboardOwner($this, 'analytics-snapshot@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Snapshot Client',
            'profile' => ['email' => 'snap@test'],
            'billing_profile' => ['billing_email' => 'factures@snapshot-client.test'],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Snapshot Opp',
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
                ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 50000],
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

        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();

        $invoice = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $invoiceId = $invoice->json('invoice_id');

        $issued = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/issue", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/payments", [
            'amount_cents' => 50000,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();

        $this->assertTrue(
            DB::table('analytics.watermarks')
                ->where('workspace_id', $owner['workspace_id'])
                ->where('source_kind', 'crm')
                ->exists()
        );
        $this->assertTrue(
            DB::table('analytics.watermarks')
                ->where('workspace_id', $owner['workspace_id'])
                ->where('source_kind', 'billing')
                ->exists()
        );

        $published = $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('profile_key', MetricKeys::PROFILE_KEY)
            ->assertJsonPath('completeness_status', MetricKeys::COMPLETENESS_COMPLETE);

        $latest = $this->getJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshot/latest", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('analytics_snapshot_id', $published->json('analytics_snapshot_id'));

        $this->assertSame(
            MetricKeys::VALUE_NO_DATA,
            $latest->json('metrics')[MetricKeys::PIPELINE_OPEN_AMOUNT]['value_status'],
        );

        $overview = $this->getJson("/api/workspaces/{$owner['workspace_id']}/analytics/overview", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('analytics_snapshot_id', $published->json('analytics_snapshot_id'))
            ->assertJsonPath('overview_profile_key', MetricKeys::PROFILE_KEY)
            ->json();

        $this->assertSame(
            MetricKeys::VALUE_NO_DATA,
            $overview['metrics'][MetricKeys::PIPELINE_OPEN_AMOUNT]['value_status'],
        );

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'analytics.snapshot_published')
                ->exists()
        );
    }
}
