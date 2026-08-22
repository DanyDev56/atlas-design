<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspacePreferencesChanged;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Modules\Workspace\Infrastructure\PostgresWorkspaceIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class ChangeWorkspacePreferencesHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly WorkspaceRepository $workspaces,
        private readonly PostgresWorkspaceIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $locale,
        string $timezone,
        string $defaultCurrency,
        string $establishmentCountry,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.preferences.change');

        $scope = 'workspace.change_preferences';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $expectedRevision,
            $locale,
            $timezone,
            $defaultCurrency,
            $establishmentCountry,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId,
            $locale,
            $timezone,
            $defaultCurrency,
            $establishmentCountry,
            $expectedRevision,
            $requestId,
            $scope,
            $fingerprint,
            $correlationId,
        ): array {
            $workspace = $this->workspaces->findById(new WorkspaceId($workspaceId));
            if ($workspace === null) {
                throw new \DomainException('Workspace not found.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $workspace->changePreferences(
                expectedRevision: $expectedRevision,
                locale: $locale,
                timezone: $timezone,
                defaultCurrency: $defaultCurrency,
                establishmentCountry: $establishmentCountry,
                now: $now,
            );
            $this->workspaces->save($workspace);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new WorkspacePreferencesChanged(
                workspaceId: $workspace->id(),
                preferencesVersion: $workspace->preferencesVersion(),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'workspace_id' => $workspace->id()->value,
                'locale' => $workspace->locale(),
                'timezone' => $workspace->timezone(),
                'default_currency' => $workspace->defaultCurrency(),
                'establishment_country' => $workspace->establishmentCountry(),
                'preferences_version' => $workspace->preferencesVersion(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
