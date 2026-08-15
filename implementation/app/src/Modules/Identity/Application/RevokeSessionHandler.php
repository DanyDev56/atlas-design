<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\SessionId;
use Atlas\Modules\Identity\Domain\SessionRevoked;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class RevokeSessionHandler
{
    public function __construct(
        private readonly PostgresSessionRepository $sessions,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{session_id: string, status: string} */
    public function handle(string $actorUserId, string $sessionId, string $requestId): array
    {
        $scope = 'identity.revoke_session';
        $fingerprint = hash('sha256', json_encode([$actorUserId, $sessionId], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $this->normalizeResponse($cached['response_payload']);
        }

        return DB::transaction(function () use ($actorUserId, $sessionId, $requestId, $scope, $fingerprint): array {
            $session = $this->sessions->findById(new SessionId($sessionId));

            if ($session === null) {
                throw new \DomainException('Session not found.');
            }

            if ($session['user_id'] !== $actorUserId) {
                throw new \DomainException('Unauthorized.');
            }

            if ($session['status'] === 'Revoked') {
                $response = [
                    'session_id' => $sessionId,
                    'status' => 'Revoked',
                ];
                $this->idempotency->store($scope, $requestId, $fingerprint, $response);

                return $this->normalizeResponse($response);
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->sessions->revoke(new SessionId($sessionId), $now);

            $event = new SessionRevoked(
                sessionId: new SessionId($sessionId),
                userId: new UserId($actorUserId),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            $response = [
                'session_id' => $sessionId,
                'status' => 'Revoked',
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $this->normalizeResponse($response);
        });
    }

    /** @param array<string, mixed> $response
     * @return array{session_id: string, status: string}
     */
    private function normalizeResponse(array $response): array
    {
        return [
            'session_id' => (string) $response['session_id'],
            'status' => (string) $response['status'],
        ];
    }
}
