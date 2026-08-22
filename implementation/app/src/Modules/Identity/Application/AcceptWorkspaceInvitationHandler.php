<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\InvitationAccepted;
use Atlas\Modules\Identity\Domain\MembershipCreated;
use Atlas\Modules\Identity\Domain\MembershipId;
use Atlas\Modules\Identity\Domain\MembershipRestored;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresInvitationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresRoleRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class AcceptWorkspaceInvitationHandler
{
    public function __construct(
        private readonly PostgresInvitationRepository $invitations,
        private readonly PostgresMembershipRepository $memberships,
        private readonly PostgresRoleRepository $roles,
        private readonly PostgresUserRepository $users,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $invitationId,
        string $token,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $scope = 'identity.accept_workspace_invitation';
        $fingerprint = hash('sha256', json_encode([
            $invitationId,
            PostgresInvitationRepository::hashToken($token),
            $actorUserId,
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
            $invitationId,
            $token,
            $requestId,
            $scope,
            $fingerprint,
            $correlationId,
        ): array {
            $invitation = $this->invitations->findByIdForUpdate($invitationId);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            if (
                $invitation === null
                || $invitation['status'] !== 'Pending'
                || new \DateTimeImmutable((string) $invitation['expires_at']) <= $now
                || ! hash_equals(
                    (string) $invitation['token_hash'],
                    PostgresInvitationRepository::hashToken($token),
                )
            ) {
                throw new \DomainException('Invitation proof invalid.');
            }

            $user = $this->users->findById(new UserId($actorUserId));
            if (
                $user === null
                || $user->status() !== User::STATUS_ACTIVE
                || $user->emailVerificationStatus() !== User::EMAIL_VERIFIED
                || PostgresInvitationRepository::normalizeEmail($user->email())
                    !== (string) $invitation['recipient_email']
            ) {
                throw new \DomainException('Invitation proof invalid.');
            }

            $roleId = new RoleId((string) $invitation['role_id']);
            if ($this->roles->findActiveById((string) $invitation['workspace_id'], $roleId) === null) {
                throw new \DomainException('Invitation role unavailable.');
            }

            $existing = $this->memberships->findByUserAndWorkspace(
                $user->id(),
                (string) $invitation['workspace_id'],
            );
            if ($existing !== null && $existing['status'] !== 'Removed') {
                throw new \DomainException('Membership already exists.');
            }

            if ($existing !== null) {
                $membershipId = new MembershipId((string) $existing['id']);
                $this->memberships->restore($membershipId, $roleId);
                $membershipEvent = new MembershipRestored(
                    membershipId: $membershipId,
                    userId: $user->id(),
                    workspaceId: (string) $invitation['workspace_id'],
                    roleId: $roleId,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                );
            } else {
                $membershipId = MembershipId::generate();
                $this->memberships->create(
                    membershipId: $membershipId,
                    userId: $user->id(),
                    workspaceId: (string) $invitation['workspace_id'],
                    roleId: $roleId,
                    now: $now,
                );
                $membershipEvent = new MembershipCreated(
                    membershipId: $membershipId,
                    userId: $user->id(),
                    workspaceId: (string) $invitation['workspace_id'],
                    roleId: $roleId,
                    eventId: EventId::generate(),
                    occurredAt: $now,
                );
            }

            $this->invitations->accept($invitationId, $actorUserId, $membershipId->value, $now);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $membershipEvent,
                correlationId: $correlationId,
            ));
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new InvitationAccepted(
                invitationId: $invitationId,
                workspaceId: (string) $invitation['workspace_id'],
                membershipId: $membershipId,
                acceptedBy: $user->id(),
                eventId: EventId::generate(),
                occurredAt: $now,
            ), correlationId: $correlationId));

            $response = [
                'invitation_id' => $invitationId,
                'workspace_id' => (string) $invitation['workspace_id'],
                'membership_id' => $membershipId->value,
                'status' => 'Accepted',
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
