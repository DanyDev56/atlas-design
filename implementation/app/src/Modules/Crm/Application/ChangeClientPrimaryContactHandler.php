<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\ClientPrimaryContactChanged;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class ChangeClientPrimaryContactHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        ?string $newPrimaryContactId,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.contacts.change-primary');

        $scope = 'crm.change_primary_contact';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $newPrimaryContactId, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $newPrimaryContactId, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if ($client->version() !== $expectedRevision) {
                throw new \DomainException('Client version conflict.');
            }

            if ($client->primaryContactId() === $newPrimaryContactId) {
                throw new \DomainException('Primary contact unchanged.');
            }

            $newContactId = $newPrimaryContactId !== null ? new ContactId($newPrimaryContactId) : null;

            if ($newContactId !== null) {
                $contact = $this->contacts->findById(
                    $workspaceId,
                    new ClientId($clientId),
                    $newContactId,
                );

                if ($contact === null || ! $contact->isActive()) {
                    throw new \DomainException('Contact reference conflict.');
                }
            }

            $previousContactId = $client->primaryContactId() !== null
                ? new ContactId($client->primaryContactId())
                : null;
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $client->changePrimaryContact($newContactId, $now);
            $this->clients->update($client);

            $event = new ClientPrimaryContactChanged(
                clientId: new ClientId($clientId),
                workspaceId: $workspaceId,
                previousPrimaryContactId: $previousContactId,
                newPrimaryContactId: $newContactId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'client_id' => $clientId,
                'primary_contact_id' => $client->primaryContactId(),
                'version' => $client->version(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
