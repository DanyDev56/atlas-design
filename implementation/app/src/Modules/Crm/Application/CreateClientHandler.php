<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Client;
use Atlas\Modules\Crm\Domain\ClientCreated;
use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateClientHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresCrmIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $profile */
    /** @param array<string, mixed>|null $billingProfile */
    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $kind,
        string $displayName,
        array $profile,
        ?array $billingProfile,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.create');

        $scope = 'crm.create_client';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId, $kind, $displayName, $profile, $billingProfile,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        if (! in_array($kind, [Client::KIND_INDIVIDUAL, Client::KIND_ORGANIZATION], true)) {
            throw new \DomainException('Invalid client kind.');
        }

        return DB::transaction(function () use (
            $workspaceId, $kind, $displayName, $profile, $billingProfile,
            $requestId, $scope, $fingerprint, $correlationId,
        ): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $clientId = ClientId::generate();
            $mergedProfile = array_merge(['display_name' => $displayName], $profile);
            $client = Client::create(
                id: $clientId,
                workspaceId: $workspaceId,
                kind: $kind,
                displayName: $displayName,
                profile: $mergedProfile,
                billingProfile: $billingProfile ?? [],
                now: $now,
            );

            $event = new ClientCreated(
                clientId: $clientId,
                workspaceId: $workspaceId,
                kind: $kind,
                status: $client->status(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->clients->insert($client);
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'client_id' => $clientId->value,
                'workspace_id' => $workspaceId,
                'kind' => $kind,
                'status' => $client->status(),
                'version' => $client->version(),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
