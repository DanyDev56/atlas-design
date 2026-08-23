<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorMfaRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Support\Facades\DB;

final class DisableOperatorMfaHandler
{
    public function __construct(
        private readonly PostgresOperatorMfaRepository $credentials,
        private readonly PostgresOperatorSessionRepository $sessions,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function handle(string $userId, string $reason): bool
    {
        return DB::transaction(function () use ($userId, $reason): bool {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $disabled = $this->credentials->disable($userId, $now);
            if (! $disabled) {
                return false;
            }

            $revokedSessions = $this->sessions->revokeAllForUser($userId, $now);
            $this->audit->record(
                action: 'operator.mfa.disabled',
                result: 'Succeeded',
                targetType: 'OperatorMfaCredential',
                targetIdHash: hash('sha256', $userId),
                reason: $reason,
                metadata: ['revoked_session_count' => $revokedSessions],
                occurredAt: $now,
            );

            return true;
        });
    }
}
