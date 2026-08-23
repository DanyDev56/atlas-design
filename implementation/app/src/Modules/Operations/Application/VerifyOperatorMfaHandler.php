<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorRecoveryCodes;
use Atlas\Modules\Operations\Domain\TotpAuthenticator;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorMfaRepository;
use Illuminate\Contracts\Encryption\Encrypter;

final class VerifyOperatorMfaHandler
{
    public function __construct(
        private readonly PostgresOperatorMfaRepository $credentials,
        private readonly TotpAuthenticator $totp,
        private readonly OperatorRecoveryCodes $recoveryCodes,
        private readonly Encrypter $encrypter,
    ) {}

    public function hasActiveCredential(string $userId): bool
    {
        return $this->credentials->findActiveForUser($userId) !== null;
    }

    public function handle(string $userId, string $code, \DateTimeImmutable $now): string
    {
        $credential = $this->credentials->findActiveForUser($userId);
        if ($credential === null || trim($code) === '') {
            throw new \DomainException('Invalid operator credentials.');
        }

        $normalizedDigits = preg_replace('/\s/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $normalizedDigits) === 1) {
            $secret = $this->encrypter->decryptString($credential['secret_ciphertext']);
            $timestep = $this->totp->verify(
                $secret,
                $normalizedDigits,
                $now,
                $credential['last_used_timestep'],
            );

            if ($timestep !== null && $this->credentials->claimTimestep($userId, $timestep, $now)) {
                return 'PasswordTotp';
            }

            throw new \DomainException('Invalid operator credentials.');
        }

        $hash = $this->recoveryCodes->hash($code);
        if ($this->credentials->consumeRecoveryCode($userId, $hash, $now)) {
            return 'PasswordRecovery';
        }

        throw new \DomainException('Invalid operator credentials.');
    }
}
