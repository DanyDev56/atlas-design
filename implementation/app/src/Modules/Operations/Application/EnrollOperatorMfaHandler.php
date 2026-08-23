<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorRecoveryCodes;
use Atlas\Modules\Operations\Domain\TotpAuthenticator;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorGrantRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorMfaRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

final class EnrollOperatorMfaHandler
{
    public function __construct(
        private readonly PostgresOperatorGrantRepository $grants,
        private readonly PostgresOperatorMfaRepository $credentials,
        private readonly PostgresOperatorSessionRepository $sessions,
        private readonly PostgresOperatorAuditRepository $audit,
        private readonly TotpAuthenticator $totp,
        private readonly OperatorRecoveryCodes $recoveryCodes,
        private readonly Encrypter $encrypter,
    ) {}

    /** @return array{secret: string, provisioning_uri: string, recovery_codes: list<string>} */
    public function handle(string $userId, string $accountLabel, string $issuer, string $reason): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($this->grants->findActiveForUser($userId, $now) === null) {
            throw new \DomainException('An active operator grant is required before MFA enrollment.');
        }

        $secret = $this->totp->generateSecret();
        $recoveryCodes = $this->recoveryCodes->generate();

        DB::transaction(function () use ($userId, $secret, $recoveryCodes, $reason, $now): void {
            $this->credentials->enroll(
                $userId,
                $this->encrypter->encryptString($secret),
                array_map($this->recoveryCodes->hash(...), $recoveryCodes),
                $now,
            );
            $revokedSessions = $this->sessions->revokeAllForUser($userId, $now);
            $this->audit->record(
                action: 'operator.mfa.enrolled',
                result: 'Succeeded',
                targetType: 'OperatorMfaCredential',
                targetIdHash: hash('sha256', $userId),
                reason: $reason,
                metadata: [
                    'recovery_code_count' => count($recoveryCodes),
                    'revoked_session_count' => $revokedSessions,
                ],
                occurredAt: $now,
            );
        });

        return [
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $accountLabel, $issuer),
            'recovery_codes' => $recoveryCodes,
        ];
    }
}
