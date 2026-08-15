<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Advisor;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class RecommendationDecisionTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_completion_is_idempotent_and_promotes_the_next_recommendation(): void
    {
        $owner = $this->onboardOwner($this, 'advisor-complete@test');
        $ids = $this->seedOverview($owner['workspace_id']);
        $requestId = (string) Str::uuid();

        $response = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/complete",
            [
                'completion_confirmation' => 'UserConfirmedActionCompleted',
                'expected_revision' => 1,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $requestId,
            ],
        )->assertOk()
            ->assertJson([
                'recommendation_id' => $ids[0],
                'status' => 'Completed',
                'revision' => 2,
                'advisor_overview_version' => 2,
                'primary_recommendation_id' => $ids[1],
            ]);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/advisor/overview", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('advisor_overview_version', 2)
            ->assertJsonPath('primary_recommendation.recommendation_id', $ids[1])
            ->assertJsonCount(1, 'alternative_recommendations');

        $this->assertDatabaseHas('advisor.recommendations', [
            'id' => $ids[0],
            'workspace_id' => $owner['workspace_id'],
            'status' => 'Completed',
            'revision' => 2,
        ]);
        $this->assertEqualsCanonicalizing(
            ['completion_confirmation' => 'UserConfirmedActionCompleted', 'actor_user_id' => $owner['user_id']],
            json_decode(
                DB::table('advisor.recommendations')->where('id', $ids[0])->value('terminal_decision'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            ),
        );
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'advisor.recommendation_completed')->count());

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[1]}/complete",
            [
                'completion_confirmation' => 'UserConfirmedActionCompleted',
                'expected_revision' => 1,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $requestId,
            ],
        )->assertConflict()
            ->assertJsonPath('messages.0', 'Idempotency conflict.');

        $retry = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/complete",
            [
                'completion_confirmation' => 'UserConfirmedActionCompleted',
                'expected_revision' => 1,
            ],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => $requestId,
            ],
        )->assertOk();

        $this->assertEqualsCanonicalizing($response->json(), $retry->json());
        $this->assertSame(2, DB::table('advisor.overviews')
            ->where('workspace_id', $owner['workspace_id'])
            ->value('advisor_overview_version'));
        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'advisor.recommendation_completed')->count());
    }

    public function test_dismissal_requires_a_structured_reason_and_revision(): void
    {
        $owner = $this->onboardOwner($this, 'advisor-dismiss@test');
        $ids = $this->seedOverview($owner['workspace_id']);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/dismiss",
            ['dismissal_reason' => 'NotNow', 'expected_revision' => 1],
            ['Authorization' => 'Bearer '.$owner['token']],
        )->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Idempotency-Key header is required.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/dismiss",
            ['dismissal_reason' => 'Free text', 'expected_revision' => 1],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertUnprocessable();

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/dismiss",
            ['dismissal_reason' => 'NotNow', 'expected_revision' => 99],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertConflict()
            ->assertJsonPath('messages.0', 'Revision conflict.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/dismiss",
            ['dismissal_reason' => 'NotNow', 'expected_revision' => 1],
            [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertOk()
            ->assertJsonPath('status', 'Dismissed')
            ->assertJsonPath('primary_recommendation_id', $ids[1]);
    }

    public function test_decision_does_not_cross_the_workspace_boundary(): void
    {
        $owner = $this->onboardOwner($this, 'advisor-owner@test');
        $otherOwner = $this->onboardOwner($this, 'advisor-other@test');
        $ids = $this->seedOverview($owner['workspace_id']);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/advisor/recommendations/{$ids[0]}/complete",
            [
                'completion_confirmation' => 'UserConfirmedActionCompleted',
                'expected_revision' => 1,
            ],
            [
                'Authorization' => 'Bearer '.$otherOwner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('advisor.recommendations', [
            'id' => $ids[0],
            'status' => 'Generated',
            'revision' => 1,
        ]);
    }

    /** @return list<string> */
    private function seedOverview(string $workspaceId): array
    {
        $assessmentId = (string) Str::uuid();
        $ids = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];
        $recommendationKeys = [
            'advisor.collect-overdue-invoices',
            'advisor.rebuild-commercial-pipeline',
            'advisor.restore-billing-momentum',
        ];

        foreach ($ids as $index => $id) {
            DB::table('advisor.recommendations')->insert([
                'id' => $id,
                'workspace_id' => $workspaceId,
                'business_health_assessment_id' => $assessmentId,
                'recommendation_key' => $recommendationKeys[$index],
                'rule_key' => 'Rule'.($index + 1),
                'status' => 'Generated',
                'revision' => 1,
                'priority' => $index === 0 ? 'High' : 'Medium',
                'rank_score' => 80 - ($index * 10),
                'action' => json_encode([
                    'module' => $index === 0 ? 'Billing' : 'CRM',
                    'route_key' => $index === 0 ? 'OverdueInvoices' : 'NewOpportunity',
                ], JSON_THROW_ON_ERROR),
                'expected_impact' => json_encode(['level' => 'Significant'], JSON_THROW_ON_ERROR),
                'urgency' => 'ThisWeek',
                'confidence' => 'High',
                'effort' => 'Small',
                'valid_until' => now()->addDay(),
                'generated_at' => now(),
            ]);
        }

        DB::table('advisor.overviews')->insert([
            'workspace_id' => $workspaceId,
            'advisor_overview_version' => 1,
            'source_eligibility' => 'Eligible',
            'business_health_assessment_id' => $assessmentId,
            'primary_recommendation_id' => $ids[0],
            'recommendation_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return $ids;
    }
}
