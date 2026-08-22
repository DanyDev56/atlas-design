<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\EmailVerificationSendRequested;
use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserCreated;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresEmailVerificationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class RegisterUserHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresEmailVerificationRepository $verificationTokens,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{user_id: string, status: string, verification_token?: string} */
    public function handle(string $email, string $displayName, string $password, string $requestId): array
    {
        $scope = 'identity.register_user';
        $fingerprint = hash('sha256', json_encode([$email, $displayName], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new \DomainException('Email address already used.');
        }

        return DB::transaction(function () use ($email, $displayName, $password, $requestId, $scope, $fingerprint): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $userId = UserId::generate();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $user = User::register($userId, $email, $displayName, $hash, $now);

            $plainToken = PostgresEmailVerificationRepository::generatePlainToken();
            $this->users->insert($user, $hash);
            $verificationTokenId = $this->verificationTokens->createToken(
                $userId->value,
                PostgresEmailVerificationRepository::hashToken($plainToken),
                $now->modify('+24 hours'),
                $plainToken,
            );

            $createdEvent = new UserCreated(
                userId: $userId,
                status: $user->status(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($createdEvent));
            $this->outbox->append(OutgoingMessage::fromDomainEvent(new EmailVerificationSendRequested(
                userId: $userId,
                deliverySecretHandle: $verificationTokenId,
                eventId: EventId::generate(),
                occurredAt: $now,
            )));

            $responseForClient = [
                'user_id' => $userId->value,
                'status' => $user->status(),
                'verification_token' => $plainToken,
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, [
                'user_id' => $userId->value,
                'status' => $user->status(),
            ]);

            return $responseForClient;
        });
    }
}
