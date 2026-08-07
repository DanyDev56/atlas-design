<?php

declare(strict_types=1);

namespace Tests\Feature\Api\BusinessHealth;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class BusinessHealthAssessmentTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_snapshot_publish_triggers_business_health_assessment(): void
    {
        $owner = $this->onboardOwner($this, 'business-health@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Health Client',
            'profile' => [],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Health Opp',
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

        $published = $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();

        $snapshotId = $published->json('analytics_snapshot_id');

        $this->assertTrue(
            DB::table('business_health.assessments')
                ->where('workspace_id', $owner['workspace_id'])
                ->where('analytics_snapshot_id', $snapshotId)
                ->exists()
        );

        $current = $this->getJson("/api/workspaces/{$owner['workspace_id']}/business-health/current", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('analytics_snapshot_id', $snapshotId)
            ->assertJsonStructure([
                'assessment_status',
                'assessment_reliability',
                'components',
                'factors',
                'risks',
            ]);

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'business_health.assessed')
                ->where('payload->analytics_snapshot_id', $snapshotId)
                ->exists()
        );

        $assessmentId = $current->json('business_health_assessment_id');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/business-health/assessments/{$assessmentId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('business_health_assessment_id', $assessmentId);
    }
}
