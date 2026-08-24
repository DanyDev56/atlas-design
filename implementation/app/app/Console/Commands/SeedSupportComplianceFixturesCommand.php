<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Composition\Demo\SupportComplianceFixtureSeeder;
use Illuminate\Console\Command;

final class SeedSupportComplianceFixturesCommand extends Command
{
    protected $signature = 'atlas:operations:seed-support-fixtures';

    protected $description = 'Create local Support and Compliance fixtures linked to the beta cohort';

    public function handle(SupportComplianceFixtureSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Support and Compliance fixtures are restricted to local and testing environments.');

            return self::FAILURE;
        }

        try {
            $result = $seeder->seed();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Support and Compliance fixtures are ready.');
        $this->table(['Module', 'Rows'], [
            ['Support cases', $result['support_cases']],
            ['Data requests', $result['data_requests']],
            ['Policy versions', $result['policy_versions']],
            ['Policy proofs', $result['policy_proofs']],
            ['Consent events', $result['consent_events']],
        ]);
        $this->line('Back-office: /backoffice/support');
        $this->comment('Fixtures only: never run this command outside local/testing.');

        return self::SUCCESS;
    }
}
