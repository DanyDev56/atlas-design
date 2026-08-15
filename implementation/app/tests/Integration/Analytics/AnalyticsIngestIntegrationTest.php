<?php

declare(strict_types=1);

namespace Tests\Integration\Analytics;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class AnalyticsIngestIntegrationTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_outbox_ingest_is_idempotent(): void
    {
        $owner = $this->onboardOwner($this, 'analytics-ingest@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Analytics Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $client->json('client_id'),
            'title' => 'Analytics Opp',
            'estimated_amount_cents' => 10000,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $processor = app(OutboxProcessor::class);
        $processor->processPending();
        $firstCount = DB::table('analytics.source_facts')
            ->where('workspace_id', $owner['workspace_id'])
            ->count();

        $this->assertGreaterThan(0, $firstCount);

        $this->patchJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunity->json('opportunity_id')}",
            [
                'changes' => ['estimated_amount_cents' => 20000],
                'expected_revision' => 1,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('version', 2);

        $processor->processPending();

        $updatedFact = DB::table('analytics.source_facts')
            ->where('workspace_id', $owner['workspace_id'])
            ->where('source_event_type', 'crm.opportunity_updated')
            ->first();

        $this->assertNotNull($updatedFact);
        $this->assertSame(2, (int) $updatedFact->aggregate_version);
        $this->assertSame(
            20000,
            json_decode($updatedFact->payload, true, 512, JSON_THROW_ON_ERROR)['estimated_amount_cents'],
        );

        $afterUpdateCount = DB::table('analytics.source_facts')
            ->where('workspace_id', $owner['workspace_id'])
            ->count();

        $processor->processPending();
        $secondCount = DB::table('analytics.source_facts')
            ->where('workspace_id', $owner['workspace_id'])
            ->count();

        $this->assertSame($afterUpdateCount, $secondCount);
    }
}
