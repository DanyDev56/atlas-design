<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Domain\Entitlement;
use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;
use Illuminate\Support\Facades\DB;

final class PostgresEntitlementRepository implements EntitlementRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Entitlement
    {
        $row = DB::table('subscriptions.entitlements')
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($row === null) {
            return null;
        }

        return new Entitlement(
            workspaceId: (string) $row->workspace_id,
            sourceType: (string) $row->source_type,
            sourceId: (string) $row->source_id,
            fullCapabilities: array_values(json_decode((string) $row->full_capabilities, true, 512, JSON_THROW_ON_ERROR)),
            restrictedCapabilities: array_values(json_decode((string) $row->restricted_capabilities, true, 512, JSON_THROW_ON_ERROR)),
            limits: json_decode((string) $row->limits, true, 512, JSON_THROW_ON_ERROR),
            validUntil: $row->valid_until !== null ? new \DateTimeImmutable((string) $row->valid_until) : null,
            computedAt: new \DateTimeImmutable((string) $row->computed_at),
            version: (int) $row->version,
        );
    }

    public function save(Entitlement $entitlement): void
    {
        DB::table('subscriptions.entitlements')->updateOrInsert(
            ['workspace_id' => $entitlement->workspaceId],
            [
                'source_type' => $entitlement->sourceType,
                'source_id' => $entitlement->sourceId,
                'full_capabilities' => json_encode($entitlement->fullCapabilities, JSON_THROW_ON_ERROR),
                'restricted_capabilities' => json_encode($entitlement->restrictedCapabilities, JSON_THROW_ON_ERROR),
                'limits' => json_encode($entitlement->limits, JSON_THROW_ON_ERROR),
                'valid_until' => $entitlement->validUntil?->format('Y-m-d H:i:sP'),
                'computed_at' => $entitlement->computedAt->format('Y-m-d H:i:sP'),
                'version' => $entitlement->version,
                'updated_at' => $entitlement->computedAt->format('Y-m-d H:i:sP'),
            ],
        );
    }
}
