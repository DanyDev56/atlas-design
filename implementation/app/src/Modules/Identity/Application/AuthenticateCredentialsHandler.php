<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;

final class AuthenticateCredentialsHandler
{
    public function __construct(
        private readonly PostgresUserRepository $users,
    ) {}

    /** @return array{user_id: string, display_name: string} */
    public function handle(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! $user->verifyPassword($password) || ! $user->canAuthenticate()) {
            throw new \DomainException('Invalid credentials.');
        }

        return [
            'user_id' => $user->id()->value,
            'display_name' => $user->displayName(),
        ];
    }
}
