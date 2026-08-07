<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    private static bool $atlasSchemasPrepared = false;

    protected function beforeRefreshingDatabase(): void
    {
        if (self::$atlasSchemasPrepared) {
            return;
        }

        DB::statement('DROP SCHEMA IF EXISTS platform CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS workspace CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS identity CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS crm CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS billing CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS analytics CASCADE');

        DB::statement('CREATE SCHEMA IF NOT EXISTS platform');
        DB::statement('CREATE SCHEMA IF NOT EXISTS workspace');
        DB::statement('CREATE SCHEMA IF NOT EXISTS identity');
        DB::statement('CREATE SCHEMA IF NOT EXISTS crm');
        DB::statement('CREATE SCHEMA IF NOT EXISTS billing');
        DB::statement('CREATE SCHEMA IF NOT EXISTS analytics');

        self::$atlasSchemasPrepared = true;
    }
}
