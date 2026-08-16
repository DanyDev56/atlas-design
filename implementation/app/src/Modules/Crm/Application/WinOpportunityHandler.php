<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Domain\OpportunityWon;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class WinOpportunityHandler
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
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.win');

        $scope = 'crm.win_opportunity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $opportunityId, Opportunity::WIN_SOURCE_MANUAL, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $actorUserId, $workspaceId, $opportunityId, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $opportunity = $this->opportunities->findByIdForUpdate(
                $workspaceId,
                new OpportunityId($opportunityId),
            );

            if ($opportunity === null) {
                throw new \DomainException('Opportunity not found.');
            }

            if ($opportunity->version() !== $expectedRevision) {
                throw new \DomainException('Opportunity version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $opportunity->win(
                source: Opportunity::WIN_SOURCE_MANUAL,
                quoteId: null,
                actorUserId: $actorUserId,
                now: $now,
            );
            $this->opportunities->update($opportunity);

            $event = new OpportunityWon(
                opportunityId: $opportunity->id(),
                workspaceId: $workspaceId,
                clientId: $opportunity->clientId(),
                source: $opportunity->winSource() ?? Opportunity::WIN_SOURCE_MANUAL,
                quoteId: null,
                version: $opportunity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'opportunity_id' => $opportunityId,
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
                'win_source' => $opportunity->winSource(),
                'won_at' => $opportunity->wonAt()?->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
