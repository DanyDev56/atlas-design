<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresCurrentBusinessHealthRepository
{
    public function upsertIfNewer(
        string $workspaceId,
        string $healthPolicyVersion,
        string $assessmentId,
        \DateTimeImmutable $asOf,
        \DateTimeImmutable $sourcePublishedAt,
    ): void {
        $existing = DB::table('business_health.current_assessments')
            ->where('workspace_id', $workspaceId)
            ->where('health_policy_version', $healthPolicyVersion)
            ->first();

        if ($existing === null) {
            DB::table('business_health.current_assessments')->insert([
                'workspace_id' => $workspaceId,
                'health_policy_version' => $healthPolicyVersion,
                'business_health_assessment_id' => $assessmentId,
                'as_of' => $asOf->format('Y-m-d H:i:sP'),
                'source_published_at' => $sourcePublishedAt->format('Y-m-d H:i:sP'),
            ]);

            return;
        }

        $existingPublishedAt = new \DateTimeImmutable($existing->source_published_at);
        if ($sourcePublishedAt >= $existingPublishedAt) {
            DB::table('business_health.current_assessments')
                ->where('workspace_id', $workspaceId)
                ->where('health_policy_version', $healthPolicyVersion)
                ->update([
                    'business_health_assessment_id' => $assessmentId,
                    'as_of' => $asOf->format('Y-m-d H:i:sP'),
                    'source_published_at' => $sourcePublishedAt->format('Y-m-d H:i:sP'),
                ]);
        }
    }

    public function findCurrent(string $workspaceId, string $healthPolicyVersion): ?array
    {
        $row = DB::table('business_health.current_assessments as c')
            ->join('business_health.assessments as a', 'a.id', '=', 'c.business_health_assessment_id')
            ->where('c.workspace_id', $workspaceId)
            ->where('c.health_policy_version', $healthPolicyVersion)
            ->select('a.*')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'business_health_assessment_id' => $row->id,
            'workspace_id' => $row->workspace_id,
            'analytics_snapshot_id' => $row->analytics_snapshot_id,
            'health_policy_version' => $row->health_policy_version,
            'as_of' => $row->as_of,
            'assessed_at' => $row->assessed_at,
            'assessment_status' => $row->assessment_status,
            'assessment_reliability' => $row->assessment_reliability,
            'overall_score' => $row->overall_score !== null ? (int) $row->overall_score : null,
            'health_band' => $row->health_band,
            'health_trend' => $row->health_trend,
            'primary_attention' => $row->primary_attention !== null
                ? json_decode($row->primary_attention, true, 512, JSON_THROW_ON_ERROR)
                : null,
            'components' => json_decode($row->components, true, 512, JSON_THROW_ON_ERROR),
            'factors' => json_decode($row->factors, true, 512, JSON_THROW_ON_ERROR),
            'risks' => json_decode($row->risks, true, 512, JSON_THROW_ON_ERROR),
            'global_coverage_percent' => (int) $row->global_coverage_percent,
            'assessment_currency' => $row->assessment_currency,
            'source_published_at' => $row->source_published_at,
        ];
    }
}
