<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Composition\Demo\BetaCohortFixtureSeeder;
use Illuminate\Console\Command;

final class SeedBetaCohortFixturesCommand extends Command
{
    protected $signature = 'atlas:beta:seed-fixtures';

    protected $description = 'Create five local beta users with progressive cohort states';

    public function handle(BetaCohortFixtureSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Beta fixtures are restricted to local and testing environments.');

            return self::FAILURE;
        }

        try {
            $participants = $seeder->seed();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Five beta test users are ready.');
        $this->table(
            ['Code', 'Email', 'Password', 'Workspace', 'Cell', 'Stage'],
            array_map(static fn (array $participant): array => [
                $participant['beta_code'],
                $participant['email'],
                $participant['password'],
                $participant['workspace_id'],
                $participant['pricing_cell'],
                $participant['current_stage'],
            ], $participants),
        );
        $this->line('Login: /app/login');
        $this->comment('Fixtures only: never reuse these credentials outside local/testing.');

        return self::SUCCESS;
    }
}
