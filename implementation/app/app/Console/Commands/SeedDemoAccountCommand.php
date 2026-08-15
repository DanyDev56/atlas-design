<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Composition\Demo\DemoAccountSeeder;
use Illuminate\Console\Command;

final class SeedDemoAccountCommand extends Command
{
    protected $signature = 'atlas:demo:seed
        {--profile=complete : Scenario to prepare: complete or empty}';

    protected $description = 'Create or upgrade a UI demo account for browser testing';

    public function handle(DemoAccountSeeder $seeder): int
    {
        $profile = (string) $this->option('profile');

        if (! in_array($profile, ['complete', 'empty'], true)) {
            $this->error('Unknown demo profile. Expected complete or empty.');

            return self::INVALID;
        }

        $result = $profile === 'empty' ? $seeder->seedEmpty() : $seeder->seed();
        $scenarioVersion = $profile === 'empty'
            ? DemoAccountSeeder::EMPTY_SCENARIO_VERSION
            : DemoAccountSeeder::SCENARIO_VERSION;

        $this->info('Demo account ready.');
        $this->line('Email: '.$result->email);
        $this->line('Password: '.$result->password);
        $this->line('Workspace: '.$result->workspaceId);
        $this->line('Profile: '.$profile);
        $this->line('Scenario: v'.$scenarioVersion);

        if ($result->userCreated) {
            $this->comment('New user created.');
        }

        if ($profile === 'empty') {
            $this->comment('Empty workspace preserved for first-run and empty-state testing.');
        } elseif ($result->sampleDataSeeded) {
            $this->comment('Demo scenario created or upgraded.');
        } else {
            $this->comment('Demo scenario already up to date.');
        }

        $labels = [
            'clients' => 'Clients',
            'opportunities' => 'Opportunities',
            'quotes' => 'Quotes',
            'invoices' => 'Invoices',
            'active_recommendations' => 'Active recommendations',
            'unread_notifications' => 'Unread notifications',
        ];
        $this->table(
            ['Resource', 'Count'],
            collect($result->resourceCounts)
                ->map(fn (int $count, string $key): array => [$labels[$key] ?? $key, $count])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->line('Ready-to-test states:');
        if ($profile === 'empty') {
            $this->line('- Dashboard without commercial or billing data');
            $this->line('- Empty CRM, Billing and Notifications');
            $this->line('- Business Health and Advisor before their first evaluation');
        } else {
            $this->line('- Ateliers du Marais: open opportunity + editable draft quote (send it to test the public link)');
            $this->line('- Maison Lumen: sent quote awaiting a response');
            $this->line('- Nova Conseil: accepted quote awaiting invoice creation');
            $this->line('- Cabinet Rivoli: draft invoice awaiting issue');
            $this->line('- Collectif Cobalt: overdue, partially-paid invoice');
            $this->line('- Horizon Digital: paid invoice history');
            $this->line('- Business Health, Advisor decisions and Notifications');
        }

        $this->line('Login: /app/login');

        return self::SUCCESS;
    }
}
