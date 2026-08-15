<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\MembershipId;
use Atlas\Modules\Identity\Domain\MembershipRemoved;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class RemoveMembershipHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresMembershipRepository $memberships,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{membership_id: string, status: string} */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $membershipId,
        string $requestId,
    ): array {
        $scope = 'identity.remove_membership';
        $fingerprint = hash('sha256', json_encode([$actorUserId, $workspaceId, $membershipId], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $this->normalizeResponse($cached['response_payload']);
        }

        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.members.remove');

        return DB::transaction(function () use ($actorUserId, $workspaceId, $membershipId, $requestId, $scope, $fingerprint): array {
            $membership = $this->memberships->findById(new MembershipId($membershipId));

            if ($membership === null || $membership['workspace_id'] !== $workspaceId) {
                throw new \DomainException('Membership not found.');
            }

            if ($membership['status'] === 'Removed') {
                $response = [
                    'membership_id' => $membershipId,
                    'status' => 'Removed',
                ];
                $this->idempotency->store($scope, $requestId, $fingerprint, $response);

                return $this->normalizeResponse($response);
            }

            if (! in_array($membership['status'], ['Active', 'Suspended'], true)) {
                throw new \DomainException('Membership state not removable.');
            }

            if ($membership['user_id'] === $actorUserId) {
                throw new \DomainException('Cannot remove own membership.');
            }

            if ($this->memberships->isActiveOwnerMembership($membershipId)
                && $this->memberships->countActiveOwners($workspaceId) <= 1) {
                throw new \DomainException('Workspace must have active owner.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->memberships->remove(new MembershipId($membershipId), $now);

            $event = new MembershipRemoved(
                membershipId: new MembershipId($membershipId),
                userId: new UserId($membership['user_id']),
                workspaceId: $workspaceId,
                roleId: new RoleId($membership['role_id']),
                previousStatus: $membership['status'],
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            $response = [
                'membership_id' => $membershipId,
                'status' => 'Removed',
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $this->normalizeResponse($response);
        });
    }

    /** @param array<string, mixed> $response
     * @return array{membership_id: string, status: string}
     */
    private function normalizeResponse(array $response): array
    {
        return [
            'membership_id' => (string) $response['membership_id'],
            'status' => (string) $response['status'],
        ];
    }
}
