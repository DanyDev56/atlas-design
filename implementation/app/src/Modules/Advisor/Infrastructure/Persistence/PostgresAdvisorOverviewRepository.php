<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresAdvisorOverviewRepository
{
    /** @param list<string> $recommendationIds */
    public function rebuild(
        string $workspaceId,
        string $sourceEligibility,
        ?string $businessHealthAssessmentId,
        ?string $primaryRecommendationId,
        array $recommendationIds,
        \DateTimeImmutable $updatedAt,
    ): int {
        $existing = DB::table('advisor.overviews')
            ->where('workspace_id', $workspaceId)
            ->first();

        $nextVersion = $existing !== null ? ((int) $existing->advisor_overview_version) + 1 : 1;

        if ($existing === null) {
            DB::table('advisor.overviews')->insert([
                'workspace_id' => $workspaceId,
                'advisor_overview_version' => $nextVersion,
                'source_eligibility' => $sourceEligibility,
                'business_health_assessment_id' => $businessHealthAssessmentId,
                'primary_recommendation_id' => $primaryRecommendationId,
                'recommendation_ids' => json_encode($recommendationIds, JSON_THROW_ON_ERROR),
                'updated_at' => $updatedAt->format('Y-m-d H:i:sP'),
            ]);
        } else {
            DB::table('advisor.overviews')
                ->where('workspace_id', $workspaceId)
                ->update([
                    'advisor_overview_version' => $nextVersion,
                    'source_eligibility' => $sourceEligibility,
                    'business_health_assessment_id' => $businessHealthAssessmentId,
                    'primary_recommendation_id' => $primaryRecommendationId,
                    'recommendation_ids' => json_encode($recommendationIds, JSON_THROW_ON_ERROR),
                    'updated_at' => $updatedAt->format('Y-m-d H:i:sP'),
                ]);
        }

        return $nextVersion;
    }

    public function findCurrent(string $workspaceId): ?array
    {
        $row = DB::table('advisor.overviews')
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'workspace_id' => $row->workspace_id,
            'advisor_overview_version' => (int) $row->advisor_overview_version,
            'source_eligibility' => $row->source_eligibility,
            'business_health_assessment_id' => $row->business_health_assessment_id,
            'primary_recommendation_id' => $row->primary_recommendation_id,
            'recommendation_ids' => json_decode($row->recommendation_ids, true, 512, JSON_THROW_ON_ERROR),
            'updated_at' => $row->updated_at,
        ];
    }
}
