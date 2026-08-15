<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\SessionCreated;
use Atlas\Modules\Identity\Domain\SessionId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class CreateSessionHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresSessionRepository $sessions,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{session_id: string, user_id: string, token: string, expires_at: string} */
    public function handle(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! $user->verifyPassword($password)) {
            throw new \DomainException('Invalid credentials.');
        }

        if (! $user->canAuthenticate()) {
            throw new \DomainException('User cannot authenticate.');
        }

        return DB::transaction(function () use ($user): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $sessionId = SessionId::generate();
            $plainToken = PostgresSessionRepository::generatePlainToken();
            $expiresAt = $now->modify('+8 hours');

            $this->sessions->create(
                sessionId: $sessionId,
                userId: $user->id(),
                tokenHash: PostgresSessionRepository::hashToken($plainToken),
                expiresAt: $expiresAt,
                createdAt: $now,
            );

            $event = new SessionCreated(
                sessionId: $sessionId,
                userId: $user->id(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            return [
                'session_id' => $sessionId->value,
                'user_id' => $user->id()->value,
                'token' => $plainToken,
                'expires_at' => $expiresAt->format(DATE_ATOM),
            ];
        });
    }
}
