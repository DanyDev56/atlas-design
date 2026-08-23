<?php

declare(strict_types=1);

namespace Atlas\Composition\Operations;

use Atlas\Modules\Identity\Application\AuthenticateCredentialsHandler;
use Atlas\Modules\Operations\Application\VerifyOperatorMfaHandler;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Support\Facades\DB;

final class ElevateOperatorSessionHandler
{
    public function __construct(
        private readonly AuthenticateCredentialsHandler $credentials,
        private readonly VerifyOperatorMfaHandler $mfa,
        private readonly PostgresOperatorSessionRepository $sessions,
        private readonly PostgresOperatorAuditRepository $audit,
        private readonly int $stepUpMinutes,
    ) {}

    public function handle(
        string $userId,
        string $sessionId,
        string $email,
        string $password,
        string $mfaCode,
        ?string $correlationId = null,
    ): array {
        $identity = $this->credentials->handle($email, $password);
        if (! hash_equals($userId, $identity['user_id'])) {
            throw new \DomainException('Invalid operator credentials.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $authenticationStrength = $this->mfa->handle($userId, $mfaCode, $now);

        return DB::transaction(function () use ($userId, $sessionId, $authenticationStrength, $correlationId, $now): array {
            if (! $this->sessions->elevate($sessionId, $userId, $authenticationStrength, $now)) {
                throw new \DomainException('Operator session is no longer active.');
            }

            $expiresAt = $now->modify('+'.max(1, min(30, $this->stepUpMinutes)).' minutes');
            $this->audit->record(
                action: 'operator.session.elevated',
                result: 'Succeeded',
                operatorUserId: $userId,
                operatorSessionId: $sessionId,
                targetType: 'OperatorSession',
                targetIdHash: hash('sha256', $sessionId),
                correlationId: $correlationId,
                metadata: ['authentication_strength' => $authenticationStrength],
                occurredAt: $now,
            );

            return [
                'authentication_strength' => $authenticationStrength,
                'mfa_verified_at' => $now->format(DATE_ATOM),
                'step_up_expires_at' => $expiresAt->format(DATE_ATOM),
            ];
        });
    }
}
