<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Infrastructure\Persistence;

use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresBusinessHealthAssessmentRepository
{
    /** @param array<string, mixed> $evaluation */
    public function insert(
        string $workspaceId,
        string $analyticsSnapshotId,
        string $healthPolicyVersion,
        \DateTimeImmutable $asOf,
        \DateTimeImmutable $assessedAt,
        \DateTimeImmutable $sourcePublishedAt,
        array $evaluation,
        array $sourceFactSummary,
        ?string $assessmentCurrency = null,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('business_health.assessments')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'analytics_snapshot_id' => $analyticsSnapshotId,
            'health_policy_version' => $healthPolicyVersion,
            'as_of' => $asOf->format('Y-m-d H:i:sP'),
            'assessed_at' => $assessedAt->format('Y-m-d H:i:sP'),
            'assessment_status' => $evaluation['assessment_status'],
            'assessment_reliability' => $evaluation['reliability'],
            'overall_score' => $evaluation['overall_score'],
            'health_band' => $evaluation['health_band'],
            'health_trend' => HealthPolicy::TREND_UNKNOWN,
            'primary_attention' => $evaluation['primary_attention'] !== null
                ? json_encode($evaluation['primary_attention'], JSON_THROW_ON_ERROR)
                : null,
            'components' => json_encode($evaluation['components'], JSON_THROW_ON_ERROR),
            'factors' => json_encode($evaluation['factors'], JSON_THROW_ON_ERROR),
            'risks' => json_encode($evaluation['risks'], JSON_THROW_ON_ERROR),
            'global_coverage_percent' => $evaluation['global_coverage_percent'],
            'source_fact_summary' => json_encode($sourceFactSummary, JSON_THROW_ON_ERROR),
            'assessment_currency' => $assessmentCurrency,
            'source_published_at' => $sourcePublishedAt->format('Y-m-d H:i:sP'),
        ]);

        return $id;
    }

    public function findByNaturalKey(
        string $workspaceId,
        string $analyticsSnapshotId,
        string $healthPolicyVersion,
    ): ?array {
        $row = DB::table('business_health.assessments')
            ->where('workspace_id', $workspaceId)
            ->where('analytics_snapshot_id', $analyticsSnapshotId)
            ->where('health_policy_version', $healthPolicyVersion)
            ->first();

        return $row !== null ? $this->mapRow((array) $row) : null;
    }

    public function findById(string $workspaceId, string $assessmentId): ?array
    {
        $row = DB::table('business_health.assessments')
            ->where('workspace_id', $workspaceId)
            ->where('id', $assessmentId)
            ->first();

        return $row !== null ? $this->mapRow((array) $row) : null;
    }

    /** @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        return [
            'business_health_assessment_id' => $row['id'],
            'workspace_id' => $row['workspace_id'],
            'analytics_snapshot_id' => $row['analytics_snapshot_id'],
            'health_policy_version' => $row['health_policy_version'],
            'as_of' => $row['as_of'],
            'assessed_at' => $row['assessed_at'],
            'assessment_status' => $row['assessment_status'],
            'assessment_reliability' => $row['assessment_reliability'],
            'overall_score' => $row['overall_score'] !== null ? (int) $row['overall_score'] : null,
            'health_band' => $row['health_band'],
            'health_trend' => $row['health_trend'],
            'primary_attention' => $row['primary_attention'] !== null
                ? json_decode($row['primary_attention'], true, 512, JSON_THROW_ON_ERROR)
                : null,
            'components' => json_decode($row['components'], true, 512, JSON_THROW_ON_ERROR),
            'factors' => json_decode($row['factors'], true, 512, JSON_THROW_ON_ERROR),
            'risks' => json_decode($row['risks'], true, 512, JSON_THROW_ON_ERROR),
            'global_coverage_percent' => (int) $row['global_coverage_percent'],
            'assessment_currency' => $row['assessment_currency'],
            'source_published_at' => $row['source_published_at'],
        ];
    }
}
