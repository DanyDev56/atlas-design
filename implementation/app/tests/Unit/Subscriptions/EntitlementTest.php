<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Domain\AccessLevel;
use Atlas\Modules\Subscriptions\Domain\Entitlement;
use PHPUnit\Framework\TestCase;

final class EntitlementTest extends TestCase
{
    public function test_expired_entitlement_keeps_only_restricted_capabilities(): void
    {
        $validUntil = new \DateTimeImmutable('2026-09-22T10:00:00+00:00');
        $entitlement = new Entitlement(
            workspaceId: 'workspace-1',
            sourceType: 'Trial',
            sourceId: 'trial-1',
            fullCapabilities: ['workspace.read', 'workspace.mutate', 'data.export'],
            restrictedCapabilities: ['workspace.read', 'data.export'],
            limits: ['members_total' => 3],
            validUntil: $validUntil,
            computedAt: new \DateTimeImmutable('2026-08-23T10:00:00+00:00'),
            version: 1,
        );

        self::assertSame(AccessLevel::Full, $entitlement->accessLevelAt($validUntil->modify('-1 second')));
        self::assertTrue($entitlement->allows('workspace.mutate', $validUntil->modify('-1 second')));
        self::assertSame(AccessLevel::Restricted, $entitlement->accessLevelAt($validUntil));
        self::assertFalse($entitlement->allows('workspace.mutate', $validUntil));
        self::assertTrue($entitlement->allows('data.export', $validUntil));
    }
}
