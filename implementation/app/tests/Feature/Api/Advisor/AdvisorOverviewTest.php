<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Advisor;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class AdvisorOverviewTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_business_health_assessment_triggers_advisor_overview(): void
    {
        $owner = $this->onboardOwner($this, 'advisor@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Advisor Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Advisor Opp',
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

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        app(OutboxProcessor::class)->processPending();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();

        $this->assertTrue(
            DB::table('advisor.overviews')
                ->where('workspace_id', $owner['workspace_id'])
                ->exists()
        );

        $overview = $this->getJson("/api/workspaces/{$owner['workspace_id']}/advisor/overview", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonStructure([
                'advisor_overview_version',
                'source_eligibility',
                'primary_recommendation',
                'alternative_recommendations',
                'updated_at',
            ]);

        $this->assertContains(
            $overview->json('source_eligibility'),
            ['Eligible', 'InsufficientAssessment', 'StaleAssessment'],
        );
    }
}
