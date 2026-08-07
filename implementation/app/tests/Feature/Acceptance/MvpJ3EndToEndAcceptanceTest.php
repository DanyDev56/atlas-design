<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\RunsMvpCommercialFlow;

final class MvpJ3EndToEndAcceptanceTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;
    use RunsMvpCommercialFlow;

    public function test_commercial_facts_produce_health_advisor_notifications_and_dashboard(): void
    {
        $owner = $this->onboardOwner($this, 'mvp-j3@test');

        $this->runMvpJ2ThroughPayment($this, $owner);
        $snapshotId = $this->publishSnapshotAndProcessDerivedPipeline($this, $owner);

        $this->assertTrue(
            DB::table('business_health.assessments')
                ->where('workspace_id', $owner['workspace_id'])
                ->where('analytics_snapshot_id', $snapshotId)
                ->exists()
        );

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'advisor.overview_changed')
                ->where('payload->workspace_id', $owner['workspace_id'])
                ->exists()
        );

        $health = $this->getJson("/api/workspaces/{$owner['workspace_id']}/business-health/current", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $advisor = $this->getJson("/api/workspaces/{$owner['workspace_id']}/advisor/overview", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/notifications/unread-count", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $dashboard = $this->getJson("/api/workspaces/{$owner['workspace_id']}/dashboard", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $dashboard->assertJsonPath('business_health.payload.business_health_assessment_id', $health->json('business_health_assessment_id'));
        $dashboard->assertJsonPath('advisor_priority.payload.advisor_overview_version', $advisor->json('advisor_overview_version'));
    }

    public function test_snapshot_publish_is_idempotent_and_outbox_replay_is_safe(): void
    {
        $owner = $this->onboardOwner($this, 'mvp-j3-idem@test');

        $this->runMvpJ2ThroughPayment($this, $owner);

        $requestId = (string) Str::uuid();
        $first = $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $requestId,
        ])->assertCreated();

        $snapshotId = $first->json('analytics_snapshot_id');

        $retry = $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $requestId,
        ])->assertCreated();

        $retry->assertJsonPath('analytics_snapshot_id', $snapshotId);

        app(\Atlas\Platform\Messaging\Infrastructure\OutboxProcessor::class)->processPending();
        app(\Atlas\Platform\Messaging\Infrastructure\OutboxProcessor::class)->processPending();
        app(\Atlas\Platform\Messaging\Infrastructure\OutboxProcessor::class)->processPending();
        app(\Atlas\Platform\Messaging\Infrastructure\OutboxProcessor::class)->processPending();

        $assessmentCount = DB::table('business_health.assessments')
            ->where('workspace_id', $owner['workspace_id'])
            ->where('analytics_snapshot_id', $snapshotId)
            ->count();

        $this->assertSame(1, $assessmentCount);

        $overviewCount = DB::table('advisor.overviews')
            ->where('workspace_id', $owner['workspace_id'])
            ->count();

        $this->assertSame(1, $overviewCount);
    }
}
