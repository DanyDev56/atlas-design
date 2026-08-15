<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Activity;
use Atlas\Modules\Crm\Domain\ActivityId;
use Atlas\Modules\Crm\Domain\ActivityRecorded;
use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresActivityRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RecordActivityHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresActivityRepository $activities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        ?string $contactId,
        ?string $opportunityId,
        string $kind,
        string $summary,
        string $occurredAt,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.activities.record');

        try {
            $activityOccurredAt = new \DateTimeImmutable($occurredAt);
        } catch (\Exception) {
            throw new \DomainException('Activity occurred at invalid.');
        }

        $kind = trim($kind);
        $summary = trim($summary);
        $normalizedOccurredAt = $activityOccurredAt
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.uP');
        $scope = 'crm.record_activity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $contactId, $opportunityId, $kind, $summary, $normalizedOccurredAt,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $contactId, $opportunityId, $kind, $summary,
            $activityOccurredAt, $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if ($contactId !== null && $this->contacts->findById(
                $workspaceId,
                new ClientId($clientId),
                new ContactId($contactId),
            ) === null) {
                throw new \DomainException('Activity contact reference conflict.');
            }

            if ($opportunityId !== null) {
                $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

                if ($opportunity === null || $opportunity->clientId()->value !== $clientId) {
                    throw new \DomainException('Activity opportunity reference conflict.');
                }
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $activity = Activity::record(
                id: ActivityId::generate(),
                workspaceId: $workspaceId,
                clientId: new ClientId($clientId),
                contactId: $contactId,
                opportunityId: $opportunityId,
                kind: $kind,
                summary: $summary,
                occurredAt: $activityOccurredAt,
                now: $now,
            );
            $event = new ActivityRecorded(
                activityId: $activity->id(),
                workspaceId: $workspaceId,
                clientId: new ClientId($clientId),
                kind: $activity->kind(),
                activityOccurredAt: $activity->occurredAt(),
                version: $activity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->activities->insert($activity);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'activity_id' => $activity->id()->value,
                'client_id' => $clientId,
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
