<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserAccountRecovered;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresAccountRecoveryRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class CompleteAccountRecoveryHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresAccountRecoveryRepository $tokens,
        private readonly PostgresSessionRepository $sessions,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{status: string} */
    public function handle(string $plainToken, string $newPassword, string $requestId): array
    {
        $scope = 'identity.complete_account_recovery';
        $fingerprint = hash('sha256', json_encode([
            PostgresAccountRecoveryRepository::hashToken($plainToken),
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return ['status' => 'recovered'];
        }

        return DB::transaction(function () use ($plainToken, $newPassword, $requestId, $scope, $fingerprint): array {
            $consumed = $this->tokens->consumeValidToken($plainToken);
            if ($consumed === null) {
                throw new \DomainException('Recovery proof invalid.');
            }

            $user = $this->users->findById(new UserId($consumed['user_id']));
            if ($user === null) {
                throw new \DomainException('Recovery proof invalid.');
            }

            if ($user->status() !== User::STATUS_ACTIVE) {
                throw new \DomainException('Recovery proof invalid.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $user->replacePassword(password_hash($newPassword, PASSWORD_BCRYPT), $now);
            $this->users->update($user);
            $this->sessions->revokeAllActiveForUser($user->id(), $now);
            $this->tokens->invalidateUnused($user->id()->value);

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new UserAccountRecovered(
                userId: $user->id(),
                securityVersion: $user->securityVersion(),
                eventId: EventId::generate(),
                occurredAt: $now,
            )));

            $response = ['status' => 'recovered'];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
