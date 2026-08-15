<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\ClientPrimaryContactChanged;
use Atlas\Modules\Crm\Domain\ContactArchived;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class ArchiveContactHandler
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
        string $contactId,
        string $reason,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.contacts.archive');

        $reason = trim($reason);
        $scope = 'crm.archive_contact';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $contactId, $reason, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $contactId, $reason, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if ($client->version() !== $expectedRevision) {
                throw new \DomainException('Client version conflict.');
            }

            $contact = $this->contacts->findById(
                $workspaceId,
                new ClientId($clientId),
                new ContactId($contactId),
            );

            if ($contact === null || ! $contact->isActive()) {
                throw new \DomainException('Contact not found.');
            }

            if ($this->opportunities->hasNonTerminalForContact($workspaceId, $clientId, $contactId)) {
                throw new \DomainException('Contact in use.');
            }

            $wasPrimary = $client->primaryContactId() === $contactId;
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $contact->archive($reason, $now);

            if ($wasPrimary) {
                $client->changePrimaryContact(null, $now);
            } else {
                $client->recordContactArchived($now);
            }

            $this->contacts->update($contact);
            $this->clients->update($client);

            $archived = new ContactArchived(
                contactId: new ContactId($contactId),
                clientId: new ClientId($clientId),
                workspaceId: $workspaceId,
                version: $contact->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($archived, correlationId: $correlationId));

            if ($wasPrimary) {
                $primaryChanged = new ClientPrimaryContactChanged(
                    clientId: new ClientId($clientId),
                    workspaceId: $workspaceId,
                    previousPrimaryContactId: new ContactId($contactId),
                    newPrimaryContactId: null,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                );
                $this->outbox->append(OutgoingMessage::fromDomainEvent(
                    $primaryChanged,
                    correlationId: $correlationId,
                ));
            }

            $response = [
                'contact_id' => $contactId,
                'client_id' => $clientId,
                'status' => $contact->status(),
                'contact_version' => $contact->version(),
                'client_version' => $client->version(),
                'primary_contact_id' => $client->primaryContactId(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
