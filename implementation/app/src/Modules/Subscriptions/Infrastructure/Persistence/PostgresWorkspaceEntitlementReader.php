<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Contracts\EntitlementDecision;
use Atlas\Modules\Subscriptions\Contracts\WorkspaceEntitlementReader;
use Atlas\Modules\Subscriptions\Domain\AccessLevel;
use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;

final class PostgresWorkspaceEntitlementReader implements WorkspaceEntitlementReader
{
    public function __construct(
        private readonly EntitlementRepository $entitlements,
    ) {}

    public function decide(string $workspaceId, string $capability): EntitlementDecision
    {
        $entitlement = $this->entitlements->findByWorkspaceId($workspaceId);

        if ($entitlement === null) {
            return new EntitlementDecision(
                workspaceId: $workspaceId,
                capability: $capability,
                granted: false,
                accessLevel: AccessLevel::Restricted->value,
                sourceType: 'None',
                validUntil: null,
                enforcementEnabled: (bool) config('subscriptions.enforcement_enabled', false),
            );
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new EntitlementDecision(
            workspaceId: $workspaceId,
            capability: $capability,
            granted: $entitlement->allows($capability, $now),
            accessLevel: $entitlement->accessLevelAt($now)->value,
            sourceType: $entitlement->sourceType,
            validUntil: $entitlement->validUntil?->format(DATE_ATOM),
            enforcementEnabled: (bool) config('subscriptions.enforcement_enabled', false),
        );
    }
}
