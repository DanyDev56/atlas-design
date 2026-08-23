<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostgresOperatorAuditRepository
{
    /** @param array<string, scalar|null> $metadata */
    public function record(
        string $action,
        string $result,
        ?string $operatorUserId = null,
        ?string $operatorSessionId = null,
        ?string $permission = null,
        ?string $targetType = null,
        ?string $targetIdHash = null,
        ?string $correlationId = null,
        ?string $reason = null,
        array $metadata = [],
        ?\DateTimeImmutable $occurredAt = null,
    ): string {
        $id = (string) Str::uuid();
        $now = $occurredAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        DB::table('operations.operator_audit_entries')->insert([
            'id' => $id,
            'operator_user_id' => $operatorUserId,
            'operator_session_id' => $operatorSessionId,
            'action' => $action,
            'permission' => $permission,
            'result' => $result,
            'target_type' => $targetType,
            'target_id_hash' => $targetIdHash,
            'correlation_id' => $correlationId,
            'reason' => $reason,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        return $id;
    }
}
