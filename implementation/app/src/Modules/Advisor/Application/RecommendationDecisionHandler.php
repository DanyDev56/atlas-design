<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Application;

use Atlas\Modules\Advisor\Domain\AdvisorOverviewChanged;
use Atlas\Modules\Advisor\Domain\RecommendationCompleted;
use Atlas\Modules\Advisor\Domain\RecommendationDismissed;
use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresAdvisorOverviewRepository;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresRecommendationRepository;
use Atlas\Modules\Advisor\Infrastructure\PostgresAdvisorIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RecommendationDecisionHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresRecommendationRepository $recommendations,
        private readonly PostgresAdvisorOverviewRepository $overviews,
        private readonly PostgresAdvisorIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function complete(
        string $actorUserId,
        string $workspaceId,
        string $recommendationId,
        string $confirmation,
        int $expectedRevision,
        string $requestId,
    ): array {
        if ($confirmation !== RecommendationPolicy::COMPLETION_CONFIRMATION) {
            throw new \DomainException('Invalid completion confirmation.');
        }

        return $this->decide(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            recommendationId: $recommendationId,
            expectedRevision: $expectedRevision,
            requestId: $requestId,
            decision: 'complete',
            structuredValue: $confirmation,
        );
    }

    /** @return array<string, mixed> */
    public function dismiss(
        string $actorUserId,
        string $workspaceId,
        string $recommendationId,
        string $reason,
        int $expectedRevision,
        string $requestId,
    ): array {
        if (! in_array($reason, RecommendationPolicy::DISMISSAL_REASONS, true)) {
            throw new \DomainException('Invalid dismissal reason.');
        }

        return $this->decide(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            recommendationId: $recommendationId,
            expectedRevision: $expectedRevision,
            requestId: $requestId,
            decision: 'dismiss',
            structuredValue: $reason,
        );
    }

    /** @return array<string, mixed> */
    private function decide(
        string $actorUserId,
        string $workspaceId,
        string $recommendationId,
        int $expectedRevision,
        string $requestId,
        string $decision,
        string $structuredValue,
    ): array {
        if ($requestId === '') {
            throw new \DomainException('Idempotency-Key header is required.');
        }

        $permission = $decision === 'complete'
            ? 'advisor.recommendations.complete'
            : 'advisor.recommendations.dismiss';
        $this->authorizer->authorize($actorUserId, $workspaceId, $permission);

        $scope = 'advisor.recommendation_'.$decision;
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $recommendationId,
            $expectedRevision,
            $structuredValue,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $actorUserId,
            $workspaceId,
            $recommendationId,
            $expectedRevision,
            $requestId,
            $decision,
            $structuredValue,
            $scope,
            $fingerprint,
        ): array {
            $this->idempotency->lock($scope, $requestId);
            $cached = $this->idempotency->find($scope, $requestId);

            if ($cached !== null) {
                if ($cached['fingerprint'] !== $fingerprint) {
                    throw new \DomainException('Idempotency conflict.');
                }

                return $cached['response_payload'];
            }

            $overview = $this->overviews->findCurrent($workspaceId);

            if ($overview === null) {
                throw new \DomainException('Overview not found.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $status = $decision === 'complete'
                ? RecommendationPolicy::STATUS_COMPLETED
                : RecommendationPolicy::STATUS_DISMISSED;
            $terminalDecision = $decision === 'complete'
                ? ['completion_confirmation' => $structuredValue, 'actor_user_id' => $actorUserId]
                : ['dismissal_reason' => $structuredValue, 'actor_user_id' => $actorUserId];

            $transition = $this->recommendations->recordTerminalDecision(
                workspaceId: $workspaceId,
                recommendationId: $recommendationId,
                expectedRevision: $expectedRevision,
                status: $status,
                terminalDecision: $terminalDecision,
                decidedAt: $now,
            );

            $remainingIds = $this->recommendations->findActiveIds(
                $workspaceId,
                $overview['recommendation_ids'],
                $now,
            );
            $primaryId = $remainingIds[0] ?? null;
            $overviewVersion = $this->overviews->rebuild(
                workspaceId: $workspaceId,
                sourceEligibility: $overview['source_eligibility'],
                businessHealthAssessmentId: $overview['business_health_assessment_id'],
                primaryRecommendationId: $primaryId,
                recommendationIds: $remainingIds,
                updatedAt: $now,
            );

            $decisionEvent = $decision === 'complete'
                ? new RecommendationCompleted(
                    workspaceId: $workspaceId,
                    recommendationId: $recommendationId,
                    revision: $transition['revision'],
                    actorUserId: $actorUserId,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                )
                : new RecommendationDismissed(
                    workspaceId: $workspaceId,
                    recommendationId: $recommendationId,
                    revision: $transition['revision'],
                    dismissalReason: $structuredValue,
                    actorUserId: $actorUserId,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                );
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $decisionEvent,
                correlationId: $requestId,
                causationId: $requestId,
            ));

            $overviewEvent = new AdvisorOverviewChanged(
                workspaceId: $workspaceId,
                advisorOverviewVersion: $overviewVersion,
                sourceEligibility: $overview['source_eligibility'],
                primaryRecommendationId: $primaryId,
                recommendationPolicyVersion: RecommendationPolicy::VERSION,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $overviewEvent,
                correlationId: $requestId,
                causationId: $decisionEvent->eventId()->value,
            ));

            $response = [
                'recommendation_id' => $recommendationId,
                'status' => $status,
                'revision' => $transition['revision'],
                'advisor_overview_version' => $overviewVersion,
                'primary_recommendation_id' => $primaryId,
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
