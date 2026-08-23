<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Application;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Support\Facades\DB;

final class RevokeOperatorSessionHandler
{
    public function __construct(
        private readonly PostgresOperatorSessionRepository $sessions,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function handle(string $userId, string $sessionId, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($userId, $sessionId, $correlationId): void {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->sessions->revoke($sessionId, $userId, $now);
            $this->audit->record(
                action: 'operator.session.revoked',
                result: 'Succeeded',
                operatorUserId: $userId,
                operatorSessionId: $sessionId,
                permission: OperatorPermissionCatalog::BACKOFFICE_ACCESS,
                targetType: 'OperatorSession',
                targetIdHash: hash('sha256', $sessionId),
                correlationId: $correlationId,
                occurredAt: $now,
            );
        });
    }
}
