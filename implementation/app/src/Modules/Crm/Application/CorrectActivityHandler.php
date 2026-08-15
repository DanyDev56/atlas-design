<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ActivityCorrected;
use Atlas\Modules\Crm\Domain\ActivityId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresActivityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CorrectActivityHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresActivityRepository $activities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $activityId,
        string $kind,
        string $summary,
        string $occurredAt,
        string $correctionReason,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.activities.correct');

        try {
            $activityOccurredAt = new \DateTimeImmutable($occurredAt);
        } catch (\Exception) {
            throw new \DomainException('Activity occurred at invalid.');
        }

        $kind = trim($kind);
        $summary = trim($summary);
        $correctionReason = trim($correctionReason);
        $normalizedOccurredAt = $activityOccurredAt
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.uP');
        $scope = 'crm.correct_activity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $activityId, $kind, $summary, $normalizedOccurredAt,
            $correctionReason, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $actorUserId, $workspaceId, $activityId, $kind, $summary, $activityOccurredAt,
            $correctionReason, $expectedRevision, $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $activity = $this->activities->findById($workspaceId, new ActivityId($activityId));

            if ($activity === null) {
                throw new \DomainException('Activity not found.');
            }

            if ($activity->version() !== $expectedRevision) {
                throw new \DomainException('Revision conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $previous = $activity->correct(
                kind: $kind,
                summary: $summary,
                occurredAt: $activityOccurredAt,
                reason: $correctionReason,
                now: $now,
            );
            $event = new ActivityCorrected(
                activityId: $activity->id(),
                workspaceId: $workspaceId,
                clientId: $activity->clientId(),
                version: $activity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->activities->updateWithRevision(
                $activity,
                $previous,
                $correctionReason,
                $actorUserId,
                $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'activity_id' => $activity->id()->value,
                'client_id' => $activity->clientId()->value,
                'contact_id' => $activity->contactId(),
                'opportunity_id' => $activity->opportunityId(),
                'kind' => $activity->kind(),
                'summary' => $activity->summary(),
                'occurred_at' => $activity->occurredAt()->format(DATE_ATOM),
                'status' => $activity->status(),
                'version' => $activity->version(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
