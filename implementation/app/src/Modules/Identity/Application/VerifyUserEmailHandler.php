<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\User;
use Atlas\Modules\Identity\Domain\UserActivated;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresEmailVerificationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class VerifyUserEmailHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresEmailVerificationRepository $verificationTokens,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{user_id: string, status: string} */
    public function handle(string $userId, string $plainToken): array
    {
        return DB::transaction(function () use ($userId, $plainToken): array {
            $user = $this->users->findById(new UserId($userId));

            if ($user === null) {
                throw new \DomainException('User not found.');
            }

            if ($user->status() === User::STATUS_ACTIVE) {
                return ['user_id' => $userId, 'status' => $user->status()];
            }

            if (! $this->verificationTokens->consumeValidToken($userId, $plainToken)) {
                throw new \DomainException('Email ownership proof invalid.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $user->verifyEmail($now);
            $this->users->update($user);

            $activatedEvent = new UserActivated(
                userId: new UserId($userId),
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($activatedEvent));

            return ['user_id' => $userId, 'status' => $user->status()];
        });
    }
}
