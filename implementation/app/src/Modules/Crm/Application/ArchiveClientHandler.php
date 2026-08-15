<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\ClientArchived;
use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class ArchiveClientHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        string $reason,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.archive');

        $reason = trim($reason);
        $scope = 'crm.archive_client';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $clientId, $reason, $expectedRevision,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $clientId, $reason, $expectedRevision,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $client = $this->clients->findById($workspaceId, new ClientId($clientId));

            if ($client === null) {
                throw new \DomainException('Client not found.');
            }

            if ($client->version() !== $expectedRevision) {
                throw new \DomainException('Client version conflict.');
            }

            if (! $client->isActive()) {
                throw new \DomainException('Client is not active.');
            }

            if ($this->opportunities->hasNonTerminalForClient($workspaceId, $clientId)) {
                throw new \DomainException('Active opportunity exists.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $client->archive($reason, $now);
            $this->clients->update($client);

            $event = new ClientArchived(
                clientId: new ClientId($clientId),
                workspaceId: $workspaceId,
                version: $client->version(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'client_id' => $clientId,
                'status' => $client->status(),
                'version' => $client->version(),
                'archived_at' => $client->archivedAt()?->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
