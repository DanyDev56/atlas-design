<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging;

use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Contracts\CreateWorkspaceCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class OutboxWorkerCommandTest extends IntegrationTestCase
{
    public function test_bounded_worker_processes_pending_messages(): void
    {
        $result = app(CreateWorkspaceHandler::class)->handle(new CreateWorkspaceCommand(
            name: 'Worker Test',
            requestedByUserId: '00000000-0000-4000-8000-000000000099',
        ));

        $this->artisan('atlas:outbox:work', [
            '--batch' => 100,
            '--sleep' => 0,
            '--max-cycles' => 1,
        ])
            ->expectsOutput('Outbox worker started.')
            ->expectsOutput('Outbox worker stopped after 1 cycle(s); processed 1 message(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('platform.outbox_messages', [
            'event_id' => $result->eventId,
            'dispatched_at' => null,
        ]);
        $this->assertSame(
            1,
            (int) DB::table('platform.spike_consumer_effects')
                ->where('consumer_name', 'spike.event_counter')
                ->value('effect_count'),
        );
    }

    public function test_worker_rejects_invalid_runtime_options(): void
    {
        $this->artisan('atlas:outbox:work', [
            '--batch' => 0,
            '--sleep' => -1,
            '--max-cycles' => -1,
        ])
            ->expectsOutput('Options must satisfy: batch >= 1, sleep >= 0 and max-cycles >= 0.')
            ->assertExitCode(Command::INVALID);
    }
}
