<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\InvitationRevoked;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresInvitationRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RevokeWorkspaceInvitationHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvitationRepository $invitations,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{invitation_id: string, status: string} */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $invitationId,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.members.invite');

        $scope = 'identity.revoke_workspace_invitation';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $invitationId,
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $actorUserId,
            $workspaceId,
            $invitationId,
            $requestId,
            $scope,
            $fingerprint,
            $correlationId,
        ): array {
            $invitation = $this->invitations->findByIdForUpdate($invitationId);
            if ($invitation === null || $invitation['workspace_id'] !== $workspaceId) {
                throw new \DomainException('Invitation not found.');
            }
            if ($invitation['status'] !== 'Pending') {
                throw new \DomainException('Invitation is not pending.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->invitations->revoke($invitationId, $now);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvitationRevoked(
                invitationId: $invitationId,
                workspaceId: $workspaceId,
                revokedBy: new UserId($actorUserId),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'invitation_id' => $invitationId,
                'status' => 'Revoked',
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
