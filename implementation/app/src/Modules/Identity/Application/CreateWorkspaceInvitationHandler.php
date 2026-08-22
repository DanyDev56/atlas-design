<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\InvitationCreated;
use Atlas\Modules\Identity\Domain\InvitationSendRequested;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresInvitationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresRoleRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class CreateWorkspaceInvitationHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvitationRepository $invitations,
        private readonly PostgresRoleRepository $roles,
        private readonly PostgresUserRepository $users,
        private readonly PostgresMembershipRepository $memberships,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $recipientEmail,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.members.invite');

        $normalizedEmail = PostgresInvitationRepository::normalizeEmail($recipientEmail);
        $scope = 'identity.create_workspace_invitation';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            $normalizedEmail,
            'member',
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
            $normalizedEmail,
            $requestId,
            $scope,
            $fingerprint,
            $correlationId,
        ): array {
            $existingUser = $this->users->findByEmail($normalizedEmail);
            if ($existingUser !== null) {
                $membership = $this->memberships->findByUserAndWorkspace($existingUser->id(), $workspaceId);
                if ($membership !== null && $membership['status'] !== 'Removed') {
                    throw new \DomainException('A membership already exists for this address.');
                }
            }

            if ($this->invitations->findPending($workspaceId, $normalizedEmail) !== null) {
                throw new \DomainException('A pending invitation already exists for this address.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $expiresAt = $now->modify('+7 days');
            $roleId = $this->roles->ensureRole(
                $workspaceId,
                'member',
                WorkspaceRoleDefaults::MEMBER_PERMISSIONS,
                $now,
            );
            $created = $this->invitations->create(
                $workspaceId,
                $normalizedEmail,
                $roleId->value,
                $actorUserId,
                $expiresAt,
            );

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvitationCreated(
                invitationId: $created['id'],
                workspaceId: $workspaceId,
                recipientEmailFingerprint: PostgresInvitationRepository::emailFingerprint($normalizedEmail),
                roleId: new RoleId($roleId->value),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvitationSendRequested(
                invitationId: $created['id'],
                workspaceId: $workspaceId,
                deliverySecretHandle: $created['id'],
                expiresAt: $expiresAt->format(DATE_ATOM),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'invitation_id' => $created['id'],
                'recipient_email' => $normalizedEmail,
                'role' => 'member',
                'status' => 'Pending',
                'delivery_status' => 'Requested',
                'expires_at' => $expiresAt->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return [...$response, 'invitation_token' => $created['token']];
        });
    }
}
