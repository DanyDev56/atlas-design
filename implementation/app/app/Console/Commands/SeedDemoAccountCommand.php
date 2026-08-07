<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Composition\Demo\DemoAccountSeeder;
use Illuminate\Console\Command;

final class SeedDemoAccountCommand extends Command
{
    protected $signature = 'atlas:demo:seed';

    protected $description = 'Create or refresh the UI demo account with sample CRM and billing data';

    public function handle(DemoAccountSeeder $seeder): int
    {
        $result = $seeder->seed();

        $this->info('Demo account ready.');
        $this->line('Email: '.$result->email);
        $this->line('Password: '.$result->password);
        $this->line('Workspace: '.$result->workspaceId);

        if ($result->userCreated) {
            $this->comment('New user created.');
        }

        if ($result->sampleDataSeeded) {
            $this->comment('Sample CRM and billing data seeded.');
        } else {
            $this->comment('Sample data skipped (workspace already has clients).');
        }

        $this->line('Login: /app/login');

        return self::SUCCESS;
    }
}
