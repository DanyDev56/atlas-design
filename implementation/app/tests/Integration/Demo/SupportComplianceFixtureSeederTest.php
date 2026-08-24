<?php

declare(strict_types=1);

namespace Tests\Integration\Demo;

use Atlas\Composition\Demo\SupportComplianceFixtureSeeder;
use Atlas\Modules\Operations\Contracts\SupportComplianceSource;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class SupportComplianceFixtureSeederTest extends IntegrationTestCase
{
    public function test_it_seeds_a_complete_idempotent_and_pseudonymized_support_scenario(): void
    {
        $first = app(SupportComplianceFixtureSeeder::class)->seed();
        $second = app(SupportComplianceFixtureSeeder::class)->seed();

        self::assertSame($first, $second);
        self::assertSame([
            'support_cases' => 8,
            'data_requests' => 4,
            'policy_versions' => 3,
            'policy_proofs' => 7,
            'consent_events' => 7,
        ], $first);
        self::assertSame(8, DB::table('operations.support_cases')->count());
        self::assertSame(4, DB::table('operations.data_requests')->count());
        self::assertSame(3, DB::table('operations.policy_versions')->count());
        self::assertSame(7, DB::table('operations.policy_acknowledgements')->count());
        self::assertSame(7, DB::table('operations.research_consent_events')->count());

        $source = app(SupportComplianceSource::class);
        $support = $source->supportPage('All', 'All', 1, 20);
        $requests = $source->dataRequestPage('All', 'All', 1, 20);
        $policies = $source->policyPage(1, 20);
        self::assertSame(8, $support['total']);
        self::assertSame(4, $requests['total']);
        self::assertSame(3, $policies['total']);
        self::assertSame(['Interview' => 3, 'Recording' => 1, 'PublicQuote' => 1], $policies['consent_counts']);
        self::assertStringStartsWith('WS-', $support['items'][0]['workspace_reference']);
        self::assertStringStartsWith('USR-', $support['items'][0]['requester_reference']);
        self::assertArrayNotHasKey('workspace_id', $support['items'][0]);
        self::assertArrayNotHasKey('evidence_fingerprint', $requests['items'][0]);
    }
}
