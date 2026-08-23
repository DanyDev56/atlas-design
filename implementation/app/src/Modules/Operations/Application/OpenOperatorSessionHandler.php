<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorGrantRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OpenOperatorSessionHandler
{
    public function __construct(
        private readonly PostgresOperatorGrantRepository $grants,
        private readonly PostgresOperatorSessionRepository $sessions,
        private readonly PostgresOperatorAuditRepository $audit,
        private readonly int $sessionMinutes,
    ) {}

    /** @return array{session_id: string, user_id: string, token: string, expires_at: string, permissions: list<string>, authentication_strength: string, mfa_verified_at: string|null, step_up_expires_at: string|null} */
    public function handle(
        string $userId,
        string $authenticationStrength,
        ?\DateTimeImmutable $mfaVerifiedAt,
        int $stepUpMinutes,
        ?string $correlationId = null,
    ): array {
        return DB::transaction(function () use ($userId, $authenticationStrength, $mfaVerifiedAt, $stepUpMinutes, $correlationId): array {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $grant = $this->grants->findActiveForUser($userId, $now);

            if ($grant === null || ! in_array(OperatorPermissionCatalog::BACKOFFICE_ACCESS, $grant['permissions'], true)) {
                throw new \DomainException('Invalid operator credentials.');
            }

            $sessionId = (string) Str::uuid();
            $plainToken = PostgresOperatorSessionRepository::generatePlainToken();
            $expiresAt = $now->modify('+'.max(5, min(120, $this->sessionMinutes)).' minutes');

            $this->sessions->create(
                $sessionId,
                $userId,
                (string) $grant['id'],
                PostgresOperatorSessionRepository::hashToken($plainToken),
                $authenticationStrength,
                $mfaVerifiedAt,
                $mfaVerifiedAt,
                $expiresAt,
                $now,
            );
            $this->audit->record(
                action: 'operator.session.opened',
                result: 'Allowed',
                operatorUserId: $userId,
                operatorSessionId: $sessionId,
                permission: OperatorPermissionCatalog::BACKOFFICE_ACCESS,
                targetType: 'OperatorSession',
                targetIdHash: hash('sha256', $sessionId),
                correlationId: $correlationId,
                metadata: [
                    'grant_version' => (int) $grant['version'],
                    'authentication_strength' => $authenticationStrength,
                ],
                occurredAt: $now,
            );

            return [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'token' => $plainToken,
                'expires_at' => $expiresAt->format(DATE_ATOM),
                'permissions' => $grant['permissions'],
                'authentication_strength' => $authenticationStrength,
                'mfa_verified_at' => $mfaVerifiedAt?->format(DATE_ATOM),
                'step_up_expires_at' => $mfaVerifiedAt?->modify('+'.max(1, min(30, $stepUpMinutes)).' minutes')->format(DATE_ATOM),
            ];
        });
    }
}
