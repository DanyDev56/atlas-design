<?php

declare(strict_types=1);

namespace Tests\Integration\Demo;

use Atlas\Composition\Demo\BetaCohortFixtureSeeder;
use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class BetaCohortFixtureSeederTest extends IntegrationTestCase
{
    public function test_it_seeds_five_login_accounts_and_progressive_beta_stages_idempotently(): void
    {
        $first = app(BetaCohortFixtureSeeder::class)->seed();
        $second = app(BetaCohortFixtureSeeder::class)->seed();

        self::assertSame(['E2', 'E3', 'E4', 'E5', 'E6'], array_column($first, 'current_stage'));
        self::assertSame($first, $second);
        self::assertSame(5, DB::table('operations.beta_participants')->count());
        self::assertSame(5, DB::table('identity.users')->where('email', 'like', 'beta-%@atlas.test')->where('email_verification_status', 'Verified')->count());
        self::assertSame(5, DB::table('workspace.workspaces')->where('name', 'like', 'Espace BETA-%')->where('status', 'Active')->count());
        self::assertSame(5, DB::table('identity.sessions')->whereIn('user_id', DB::table('identity.users')->where('email', 'like', 'beta-%@atlas.test')->select('id'))->distinct('user_id')->count('user_id'));
        self::assertSame(['P19', 'P24', 'P29', 'P19', 'P24'], array_column($first, 'pricing_cell'));

        $participants = app(BetaCohortSource::class)->participants(new \DateTimeImmutable('now'), 7);
        self::assertTrue(collect($participants)->firstWhere('beta_code', 'BETA-002')['blocked']);
        self::assertSame('TRIAL_COMMITTED', collect($participants)->firstWhere('beta_code', 'BETA-005')['pricing_decision']['decision']);
    }
}
