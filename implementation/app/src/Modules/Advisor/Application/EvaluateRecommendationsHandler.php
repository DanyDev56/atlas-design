<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Application;

use Atlas\Modules\Advisor\Domain\AdvisorOverviewChanged;
use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresAdvisorOverviewRepository;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresRecommendationRepository;
use Atlas\Modules\Advisor\Infrastructure\PostgresAdvisorIdempotencyStore;
use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresCurrentBusinessHealthRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class EvaluateRecommendationsHandler
{
    public function __construct(
        private readonly PostgresCurrentBusinessHealthRepository $currentHealth,
        private readonly PostgresRecommendationRepository $recommendations,
        private readonly PostgresAdvisorOverviewRepository $overviews,
        private readonly PostgresAdvisorIdempotencyStore $idempotency,
        private readonly RecommendationPolicyEvaluator $evaluator,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $workspaceId,
        string $businessHealthAssessmentId,
        string $requestId,
    ): array {
        $scope = 'advisor.evaluate_recommendations';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $businessHealthAssessmentId, RecommendationPolicy::VERSION,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $businessHealthAssessmentId, $requestId, $scope, $fingerprint,
        ): array {
            $assessment = $this->currentHealth->findCurrent($workspaceId, HealthPolicy::VERSION);

            if ($assessment === null) {
                throw new \DomainException('Assessment not found.');
            }

            if ($assessment['business_health_assessment_id'] !== $businessHealthAssessmentId) {
                throw new \DomainException('Stale assessment.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $asOf = new \DateTimeImmutable($assessment['as_of']);
            $evaluation = $this->evaluator->evaluate($assessment, $asOf, $now);

            $this->recommendations->expireGeneratedForWorkspace($workspaceId);

            $recommendationIds = [];
            $primaryId = null;

            foreach ($evaluation['recommendations'] as $candidate) {
                $id = $this->recommendations->insert(
                    workspaceId: $workspaceId,
                    businessHealthAssessmentId: $businessHealthAssessmentId,
                    candidate: $candidate,
                    generatedAt: $now,
                );
                $recommendationIds[] = $id;

                if ($primaryId === null && $candidate['recommendation_key'] === $evaluation['primary_recommendation_key']) {
                    $primaryId = $id;
                }
            }

            $overviewVersion = $this->overviews->rebuild(
                workspaceId: $workspaceId,
                sourceEligibility: $evaluation['source_eligibility'],
                businessHealthAssessmentId: $businessHealthAssessmentId,
                primaryRecommendationId: $primaryId,
                recommendationIds: $recommendationIds,
                updatedAt: $now,
            );

            $event = new AdvisorOverviewChanged(
                workspaceId: $workspaceId,
                advisorOverviewVersion: $overviewVersion,
                sourceEligibility: $evaluation['source_eligibility'],
                primaryRecommendationId: $primaryId,
                recommendationPolicyVersion: RecommendationPolicy::VERSION,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            $response = [
                'advisor_overview_version' => $overviewVersion,
                'source_eligibility' => $evaluation['source_eligibility'],
                'primary_recommendation_key' => $evaluation['primary_recommendation_key'],
                'recommendation_count' => count($recommendationIds),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
