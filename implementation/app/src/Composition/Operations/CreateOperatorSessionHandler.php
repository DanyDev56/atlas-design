<?php

declare(strict_types=1);

namespace Atlas\Composition\Operations;

use Atlas\Modules\Identity\Application\AuthenticateCredentialsHandler;
use Atlas\Modules\Operations\Application\OpenOperatorSessionHandler;

final class CreateOperatorSessionHandler
{
    public function __construct(
        private readonly AuthenticateCredentialsHandler $credentials,
        private readonly OpenOperatorSessionHandler $sessions,
    ) {}

    /** @return array{session_id: string, user_id: string, display_name: string, token: string, expires_at: string, permissions: list<string>} */
    public function handle(string $email, string $password, ?string $correlationId = null): array
    {
        $identity = $this->credentials->handle($email, $password);
        $session = $this->sessions->handle($identity['user_id'], $correlationId);

        return [
            ...$session,
            'display_name' => $identity['display_name'],
        ];
    }
}
