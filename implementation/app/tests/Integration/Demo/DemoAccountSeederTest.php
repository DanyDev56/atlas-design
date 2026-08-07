<?php

declare(strict_types=1);

namespace Tests\Integration\Demo;

use Atlas\Composition\Demo\DemoAccountSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class DemoAccountSeederTest extends IntegrationTestCase
{
    public function test_seeds_demo_account_with_crm_and_billing_sample_data(): void
    {
        $result = app(DemoAccountSeeder::class)->seed();

        $this->assertSame(DemoAccountSeeder::EMAIL, $result->email);
        $this->assertTrue($result->sampleDataSeeded);

        $this->assertSame(2, DB::table('crm.clients')->where('workspace_id', $result->workspaceId)->count());
        $this->assertTrue(
            DB::table('billing.quotes')->where('workspace_id', $result->workspaceId)->where('status', 'Accepted')->exists()
        );

        $second = app(DemoAccountSeeder::class)->seed();
        $this->assertFalse($second->sampleDataSeeded);
    }
}
