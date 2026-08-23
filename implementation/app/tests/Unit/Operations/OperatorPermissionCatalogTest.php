<?php

declare(strict_types=1);

namespace Tests\Unit\Operations;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use PHPUnit\Framework\TestCase;

final class OperatorPermissionCatalogTest extends TestCase
{
    public function test_permissions_are_normalized_and_require_explicit_backoffice_access(): void
    {
        self::assertSame([
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
        ], OperatorPermissionCatalog::normalize([
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
        ]));
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->expectException(\DomainException::class);
        OperatorPermissionCatalog::normalize([
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            'operations.admin',
        ]);
    }
}
