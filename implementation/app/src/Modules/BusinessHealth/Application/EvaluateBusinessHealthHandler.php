<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Application;

use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsSnapshotRepository;
use Atlas\Modules\BusinessHealth\Domain\BusinessHealthAssessed;
use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresBusinessHealthAssessmentRepository;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresCurrentBusinessHealthRepository;
use Atlas\Modules\BusinessHealth\Infrastructure\PostgresBusinessHealthIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class EvaluateBusinessHealthHandler
{
    public function __construct(
        private readonly PostgresAnalyticsSnapshotRepository $snapshots,
        private readonly PostgresBusinessHealthAssessmentRepository $assessments,
        private readonly PostgresCurrentBusinessHealthRepository $current,
        private readonly PostgresBusinessHealthIdempotencyStore $idempotency,
        private readonly HealthPolicyEvaluator $evaluator,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $workspaceId,
        string $analyticsSnapshotId,
        string $healthPolicyVersion,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        if ($healthPolicyVersion !== HealthPolicy::VERSION) {
            throw new \DomainException('Unsupported policy version.');
        }

        $scope = 'business_health.evaluate';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $analyticsSnapshotId, $healthPolicyVersion,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        $existing = $this->assessments->findByNaturalKey(
            $workspaceId,
            $analyticsSnapshotId,
            $healthPolicyVersion,
        );

        if ($existing !== null) {
            return $this->responseFromAssessment($existing);
        }

        return DB::transaction(function () use (
            $workspaceId, $analyticsSnapshotId, $healthPolicyVersion,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $snapshot = $this->snapshots->findById($workspaceId, $analyticsSnapshotId);

            if ($snapshot === null) {
                throw new \DomainException('Snapshot not found.');
            }

            if ($snapshot['profile_key'] !== MetricKeys::PROFILE_KEY
                || $snapshot['profile_version'] !== MetricKeys::PROFILE_VERSION) {
                throw new \DomainException('Unsupported snapshot profile.');
            }

            $freshness = $snapshot['freshness_status'] === MetricKeys::FRESHNESS_CURRENT
                ? HealthPolicy::FRESHNESS_CURRENT
                : 'Lagging';
            $completeness = $snapshot['completeness_status'] === MetricKeys::COMPLETENESS_COMPLETE
                ? HealthPolicy::COMPLETENESS_COMPLETE
                : 'Incomplete';

            $sourceFactSummary = $snapshot['source_fact_summary'] ?? [];
            $evaluation = $this->evaluator->evaluate($sourceFactSummary, $freshness, $completeness);

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $asOf = new \DateTimeImmutable($snapshot['as_of']);
            $sourcePublishedAt = new \DateTimeImmutable($snapshot['published_at']);

            $assessmentId = $this->assessments->insert(
                workspaceId: $workspaceId,
                analyticsSnapshotId: $analyticsSnapshotId,
                healthPolicyVersion: $healthPolicyVersion,
                asOf: $asOf,
                assessedAt: $now,
                sourcePublishedAt: $sourcePublishedAt,
                evaluation: $evaluation,
                sourceFactSummary: $sourceFactSummary,
                assessmentCurrency: 'EUR',
            );

            $this->current->upsertIfNewer(
                workspaceId: $workspaceId,
                healthPolicyVersion: $healthPolicyVersion,
                assessmentId: $assessmentId,
                asOf: $asOf,
                sourcePublishedAt: $sourcePublishedAt,
                sourceIsCurrent: $freshness === HealthPolicy::FRESHNESS_CURRENT,
            );

            $primaryAttentionKey = $evaluation['primary_attention']['factor_key'] ?? null;

            $event = new BusinessHealthAssessed(
                businessHealthAssessmentId: $assessmentId,
                analyticsSnapshotId: $analyticsSnapshotId,
                workspaceId: $workspaceId,
                healthPolicyVersion: $healthPolicyVersion,
                assessmentStatus: $evaluation['assessment_status'],
                assessmentReliability: $evaluation['reliability'],
                overallScore: $evaluation['overall_score'],
                healthBand: $evaluation['health_band'],
                healthTrend: HealthPolicy::TREND_UNKNOWN,
                primaryAttentionFactorKey: $primaryAttentionKey,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'business_health_assessment_id' => $assessmentId,
                'analytics_snapshot_id' => $analyticsSnapshotId,
                'health_policy_version' => $healthPolicyVersion,
                'assessment_status' => $evaluation['assessment_status'],
                'assessment_reliability' => $evaluation['reliability'],
                'overall_score' => $evaluation['overall_score'],
                'health_band' => $evaluation['health_band'],
                'health_trend' => HealthPolicy::TREND_UNKNOWN,
                'primary_attention' => $evaluation['primary_attention'],
                'global_coverage_percent' => $evaluation['global_coverage_percent'],
                'risks' => $evaluation['risks'],
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /** @param array<string, mixed> $assessment */
    private function responseFromAssessment(array $assessment): array
    {
        return [
            'business_health_assessment_id' => $assessment['business_health_assessment_id'],
            'analytics_snapshot_id' => $assessment['analytics_snapshot_id'],
            'health_policy_version' => $assessment['health_policy_version'],
            'assessment_status' => $assessment['assessment_status'],
            'assessment_reliability' => $assessment['assessment_reliability'],
            'overall_score' => $assessment['overall_score'],
            'health_band' => $assessment['health_band'],
            'health_trend' => $assessment['health_trend'],
            'primary_attention' => $assessment['primary_attention'],
            'global_coverage_percent' => $assessment['global_coverage_percent'],
            'risks' => $assessment['risks'],
        ];
    }
}
