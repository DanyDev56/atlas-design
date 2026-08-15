<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Composition\Demo\DemoAccountSeeder;
use Illuminate\Console\Command;

final class SeedDemoAccountCommand extends Command
{
    protected $signature = 'atlas:demo:seed';

    protected $description = 'Create or upgrade the UI demo account with a complete test scenario';

    public function handle(DemoAccountSeeder $seeder): int
    {
        $result = $seeder->seed();

        $this->info('Demo account ready.');
        $this->line('Email: '.$result->email);
        $this->line('Password: '.$result->password);
        $this->line('Workspace: '.$result->workspaceId);
        $this->line('Scenario: v'.DemoAccountSeeder::SCENARIO_VERSION);

        if ($result->userCreated) {
            $this->comment('New user created.');
        }

        if ($result->sampleDataSeeded) {
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
        $this->line('- Ateliers du Marais: open opportunity + editable draft quote (send it to test the public link)');
        $this->line('- Maison Lumen: sent quote awaiting a response');
        $this->line('- Nova Conseil: accepted quote awaiting invoice creation');
        $this->line('- Cabinet Rivoli: draft invoice awaiting issue');
        $this->line('- Collectif Cobalt: overdue, partially-paid invoice');
        $this->line('- Horizon Digital: paid invoice history');
        $this->line('- Business Health, Advisor decisions and Notifications');

        $this->line('Login: /app/login');

        return self::SUCCESS;
    }
}
