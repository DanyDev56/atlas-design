<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\SessionElevated;
use Atlas\Modules\Identity\Domain\SessionId;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class ElevateSessionHandler
{
    public const SCOPE = 'PermissionScoped';

    /** @var list<string> */
    public const PERMISSIONS = [
        'crm.clients.import-history',
        'billing.history.import',
        'workspace.billing-identity.update',
    ];

    public const LIFETIME = '+15 minutes';

    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresSessionRepository $sessions,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{session_id: string, elevation_scope: string, elevation_expires_at: string} */
    public function handle(
        string $actorUserId,
        string $sessionId,
        string $password,
        string $requestId,
    ): array {
        $scope = 'identity.elevate_session';
        $fingerprint = hash('sha256', json_encode([$actorUserId, $sessionId], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $this->normalizeResponse($cached['response_payload']);
        }

        $user = $this->users->findById(new UserId($actorUserId));
        if ($user === null || ! $user->verifyPassword($password)) {
            throw new \DomainException('Invalid credentials.');
        }
        if (! $user->canAuthenticate()) {
            throw new \DomainException('User cannot authenticate.');
        }

        $session = $this->sessions->findById(new SessionId($sessionId));
        if ($session === null || $session['status'] !== 'Active' || $session['user_id'] !== $actorUserId) {
            throw new \DomainException('Unauthorized.');
        }

        return DB::transaction(function () use (
            $actorUserId,
            $sessionId,
            $session,
            $requestId,
            $scope,
            $fingerprint,
        ): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $expiresAt = $now->modify(self::LIFETIME);
            $version = ((int) ($session['elevation_version'] ?? 0)) + 1;

            $this->sessions->activateElevation(
                sessionId: new SessionId($sessionId),
                permissions: self::PERMISSIONS,
                expiresAt: $expiresAt,
                version: $version,
            );

            $this->outbox->append(OutgoingMessage::fromDomainEvent(new SessionElevated(
                sessionId: new SessionId($sessionId),
                userId: new UserId($actorUserId),
                elevationScope: self::SCOPE,
                elevationExpiresAt: $expiresAt,
                eventId: EventId::generate(),
                occurredAt: $now,
            )));

            $response = [
                'session_id' => $sessionId,
                'elevation_scope' => self::SCOPE,
                'elevation_expires_at' => $expiresAt->format(DATE_ATOM),
            ];
            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }

    /**
     * @param array<string, mixed> $response
     * @return array{session_id: string, elevation_scope: string, elevation_expires_at: string}
     */
    private function normalizeResponse(array $response): array
    {
        return [
            'session_id' => (string) $response['session_id'],
            'elevation_scope' => (string) $response['elevation_scope'],
            'elevation_expires_at' => (string) $response['elevation_expires_at'],
        ];
    }
}
