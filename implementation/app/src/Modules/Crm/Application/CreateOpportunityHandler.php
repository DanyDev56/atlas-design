<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityCreated;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateOpportunityHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        ?string $contactId,
        string $title,
        ?int $estimatedAmountCents,
        string $currency,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.create');

        $scope = 'crm.create_opportunity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $contactId, $title, $estimatedAmountCents, $currency,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $contactId, $title, $estimatedAmountCents, $currency,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if ($contactId !== null) {
                $contact = $this->contacts->findById(
                    $workspaceId,
                    new ClientId($clientId),
                    new ContactId($contactId),
                );

                if ($contact === null || ! $contact->isActive()) {
                    throw new \DomainException('Contact reference conflict.');
                }
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $opportunityId = OpportunityId::generate();
            $opportunity = Opportunity::create(
                id: $opportunityId,
                workspaceId: $workspaceId,
                clientId: new ClientId($clientId),
                contactId: $contactId,
                title: $title,
                estimatedAmountCents: $estimatedAmountCents,
                currency: strtoupper($currency),
                now: $now,
            );

            $event = new OpportunityCreated(
                opportunityId: $opportunityId,
                workspaceId: $workspaceId,
                clientId: new ClientId($clientId),
                status: $opportunity->status(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->opportunities->insert($opportunity);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'opportunity_id' => $opportunityId->value,
                'client_id' => $clientId,
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
