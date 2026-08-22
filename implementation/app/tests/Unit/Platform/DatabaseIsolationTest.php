<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;

final class DatabaseIsolationTest extends TestCase
{
    public function test_phpunit_forces_the_isolated_database(): void
    {
        $this->assertSame('atlas_test', getenv('DB_DATABASE'));
        $this->assertSame('array', getenv('MAIL_MAILER'));
    }
}
