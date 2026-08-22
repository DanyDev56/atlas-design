<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceProfileUpdated;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Modules\Workspace\Infrastructure\PostgresWorkspaceIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class UpdateWorkspaceProfileHandler
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
        string $displayName,
        ?string $tradingName,
        ?string $activityDescription,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.profile.update');

        $scope = 'workspace.update_profile';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $expectedRevision,
            $displayName,
            $tradingName,
            $activityDescription,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $this->normalizeProfile($cached['response_payload']);
        }

        return DB::transaction(function () use (
            $workspaceId,
            $displayName,
            $tradingName,
            $activityDescription,
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
            $workspace->updateProfile(
                expectedRevision: $expectedRevision,
                displayName: $displayName,
                tradingName: $tradingName,
                activityDescription: $activityDescription,
                now: $now,
            );
            $this->workspaces->save($workspace);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new WorkspaceProfileUpdated(
                workspaceId: $workspace->id(),
                profileVersion: $workspace->profileVersion(),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'workspace_id' => $workspace->id()->value,
                'display_name' => $workspace->name(),
                'trading_name' => $workspace->tradingName(),
                'activity_description' => $workspace->activityDescription(),
                'profile_version' => $workspace->profileVersion(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $this->normalizeProfile($response);
        });
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function normalizeProfile(array $response): array
    {
        return [
            'workspace_id' => (string) $response['workspace_id'],
            'display_name' => (string) $response['display_name'],
            'trading_name' => $response['trading_name'] !== null ? (string) $response['trading_name'] : null,
            'activity_description' => $response['activity_description'] !== null ? (string) $response['activity_description'] : null,
            'profile_version' => (int) $response['profile_version'],
        ];
    }
}
