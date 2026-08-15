<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ActivityId;
use Atlas\Modules\Crm\Domain\ActivityRemoved;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresActivityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RemoveActivityHandler
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
        string $removalReason,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.activities.remove');

        $removalReason = trim($removalReason);
        $scope = 'crm.remove_activity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $activityId, $removalReason, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $actorUserId, $workspaceId, $activityId, $removalReason, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $activity = $this->activities->findById($workspaceId, new ActivityId($activityId));

            if ($activity === null) {
                throw new \DomainException('Activity not found.');
            }

            if ($activity->version() !== $expectedRevision) {
                throw new \DomainException('Revision conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $activity->remove($removalReason, $actorUserId, $now);
            $this->activities->updateRemoval($activity);

            $event = new ActivityRemoved(
                activityId: $activity->id(),
                workspaceId: $workspaceId,
                clientId: $activity->clientId(),
                version: $activity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'activity_id' => $activity->id()->value,
                'status' => $activity->status(),
                'version' => $activity->version(),
                'removed_at' => $activity->removedAt()?->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
