<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Domain\OpportunityUpdated;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class UpdateOpportunityHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $changes */
    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $opportunityId,
        array $changes,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.update');

        ksort($changes);
        $scope = 'crm.update_opportunity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $opportunityId, $changes, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $opportunityId, $changes, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

            if ($opportunity === null) {
                throw new \DomainException('Opportunity not found.');
            }

            if (! $opportunity->isNonTerminal()) {
                throw new \DomainException('Opportunity is terminal.');
            }

            if ($opportunity->version() !== $expectedRevision) {
                throw new \DomainException('Opportunity version conflict.');
            }

            $client = $this->clients->findById($workspaceId, $opportunity->clientId());

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if (array_key_exists('contact_id', $changes) && $changes['contact_id'] !== null) {
                $contact = $this->contacts->findById(
                    $workspaceId,
                    $opportunity->clientId(),
                    new ContactId($changes['contact_id']),
                );

                if ($contact === null || ! $contact->isActive()) {
                    throw new \DomainException('Contact reference conflict.');
                }
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $opportunity->updateDetails($changes, $now);
            $this->opportunities->update($opportunity);

            $event = new OpportunityUpdated(
                opportunityId: new OpportunityId($opportunityId),
                workspaceId: $workspaceId,
                clientId: new ClientId($opportunity->clientId()->value),
                version: $opportunity->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'opportunity_id' => $opportunityId,
                'workspace_id' => $workspaceId,
                'client_id' => $opportunity->clientId()->value,
                'contact_id' => $opportunity->contactId(),
                'title' => $opportunity->title(),
                'estimated_amount_cents' => $opportunity->estimatedAmountCents(),
                'currency' => $opportunity->currency(),
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
                'qualified_at' => $opportunity->qualifiedAt()?->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
