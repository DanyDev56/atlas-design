<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\Contact;
use Atlas\Modules\Crm\Domain\ContactAdded;
use Atlas\Modules\Crm\Domain\ContactId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class AddContactHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $profile */
    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        array $profile,
        bool $makePrimary,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.contacts.create');

        if ($makePrimary) {
            $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.contacts.change-primary');
        }

        $scope = 'crm.add_contact';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $profile, $makePrimary, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $profile, $makePrimary, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null || ! $client->isActive()) {
                throw new \DomainException('Client not found.');
            }

            if ($client->version() !== $expectedRevision) {
                throw new \DomainException('Client version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $contactId = ContactId::generate();
            $contact = Contact::create(
                id: $contactId,
                workspaceId: $workspaceId,
                clientId: new ClientId($clientId),
                profile: $profile,
                now: $now,
            );

            $this->contacts->insertWithWorkspace($contact, $workspaceId);

            if ($makePrimary) {
                $client->assignPrimaryContact($contactId, $now);
                $this->clients->update($client);
            }

            $event = new ContactAdded(
                contactId: $contactId,
                clientId: new ClientId($clientId),
                workspaceId: $workspaceId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'contact_id' => $contactId->value,
                'client_id' => $clientId,
                'is_primary' => $makePrimary,
                'client_version' => $client->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
