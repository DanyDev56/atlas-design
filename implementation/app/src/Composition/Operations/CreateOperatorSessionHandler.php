<?php

declare(strict_types=1);

namespace Atlas\Composition\Operations;

use Atlas\Modules\Identity\Application\AuthenticateCredentialsHandler;
use Atlas\Modules\Operations\Application\OpenOperatorSessionHandler;
use Atlas\Modules\Operations\Application\VerifyOperatorMfaHandler;

final class CreateOperatorSessionHandler
{
    public function __construct(
        private readonly AuthenticateCredentialsHandler $credentials,
        private readonly VerifyOperatorMfaHandler $mfa,
        private readonly OpenOperatorSessionHandler $sessions,
        private readonly bool $requireMfa,
        private readonly int $stepUpMinutes,
    ) {}

    /** @return array{session_id: string, user_id: string, display_name: string, token: string, expires_at: string, permissions: list<string>, authentication_strength: string, mfa_verified_at: string|null, step_up_expires_at: string|null} */
    public function handle(
        string $email,
        string $password,
        ?string $mfaCode = null,
        ?string $correlationId = null,
    ): array {
        $identity = $this->credentials->handle($email, $password);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hasMfa = $this->mfa->hasActiveCredential($identity['user_id']);

        if ($hasMfa) {
            $authenticationStrength = $this->mfa->handle($identity['user_id'], (string) $mfaCode, $now);
            $mfaVerifiedAt = $now;
        } elseif ($this->requireMfa) {
            throw new \DomainException('Invalid operator credentials.');
        } else {
            $authenticationStrength = 'PasswordOnly';
            $mfaVerifiedAt = null;
        }

        $session = $this->sessions->handle(
            $identity['user_id'],
            $authenticationStrength,
            $mfaVerifiedAt,
            $this->stepUpMinutes,
            $correlationId,
        );

        return [
            ...$session,
            'display_name' => $identity['display_name'],
        ];
    }
}
