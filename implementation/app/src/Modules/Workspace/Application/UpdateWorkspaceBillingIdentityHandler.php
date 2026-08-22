<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Domain\WorkspaceBillingIdentityUpdated;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Modules\Workspace\Infrastructure\PostgresWorkspaceIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class UpdateWorkspaceBillingIdentityHandler
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
        string $sessionId,
        ?string $legalName,
        ?string $administrativeEmail,
        int $expectedRevision,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorizeElevated(
            $actorUserId,
            $workspaceId,
            'workspace.billing-identity.update',
            $sessionId,
        );

        $scope = 'workspace.update_billing_identity';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $expectedRevision,
            $legalName,
            $administrativeEmail,
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
            $legalName,
            $administrativeEmail,
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
            $workspace->updateBillingIdentity(
                expectedRevision: $expectedRevision,
                legalName: $legalName,
                administrativeEmail: $administrativeEmail,
                now: $now,
            );
            $this->workspaces->save($workspace);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new WorkspaceBillingIdentityUpdated(
                workspaceId: $workspace->id(),
                billingIdentityVersion: $workspace->billingIdentityVersion(),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'workspace_id' => $workspace->id()->value,
                'legal_name' => $workspace->legalName(),
                'administrative_email' => $workspace->administrativeEmail(),
                'billing_identity_version' => $workspace->billingIdentityVersion(),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
