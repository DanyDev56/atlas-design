<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Domain\OpportunityLost;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class LoseOpportunityHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $opportunityId,
        string $lossReasonCode,
        ?string $lossNote,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.lose');

        $lossNote = $lossNote !== null ? trim($lossNote) : null;
        $lossNote = $lossNote !== '' ? $lossNote : null;
        $scope = 'crm.lose_opportunity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $opportunityId, $lossReasonCode, $lossNote, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $opportunityId, $lossReasonCode, $lossNote, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

            if ($opportunity === null) {
                throw new \DomainException('Opportunity not found.');
            }

            if ($opportunity->version() !== $expectedRevision) {
                throw new \DomainException('Opportunity version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $opportunity->lose($lossReasonCode, $lossNote, $now);
            $this->opportunities->update($opportunity);

            $event = new OpportunityLost(
                opportunityId: new OpportunityId($opportunityId),
                workspaceId: $workspaceId,
                clientId: $opportunity->clientId(),
                lossReasonCode: $opportunity->lossReasonCode() ?? $lossReasonCode,
                version: $opportunity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'opportunity_id' => $opportunityId,
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
                'loss_reason_code' => $opportunity->lossReasonCode(),
                'lost_at' => $opportunity->lostAt()?->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
