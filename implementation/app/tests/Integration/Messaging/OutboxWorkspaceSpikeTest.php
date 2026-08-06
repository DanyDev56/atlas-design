<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging;

use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Contracts\CreateWorkspaceCommand;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class OutboxWorkspaceSpikeTest extends IntegrationTestCase
{
    public function test_workspace_creation_persists_outbox_atomically(): void
    {
        $handler = app(CreateWorkspaceHandler::class);
        $result = $handler->handle(new CreateWorkspaceCommand(
            name: 'Spike Workspace',
            requestedByUserId: '00000000-0000-4000-8000-000000000099',
        ));

        $this->assertDatabaseHas('workspace.workspaces', [
            'id' => $result->workspaceId,
            'name' => 'Spike Workspace',
        ]);

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $result->eventId,
            'event_type' => 'workspace.workspace_created',
        ]);
    }

    public function test_inbox_deduplication_prevents_double_effect_on_replay(): void
    {
        $handler = app(CreateWorkspaceHandler::class);
        $result = $handler->handle(new CreateWorkspaceCommand(
            name: 'Replay Test',
            requestedByUserId: '00000000-0000-4000-8000-000000000099',
        ));

        $processor = app(OutboxProcessor::class);
        $processor->processPending();

        DB::table('platform.outbox_messages')
            ->where('event_id', $result->eventId)
            ->update(['dispatched_at' => null]);

        $processor->processPending();

        $this->assertSame(
            1,
            (int) DB::table('platform.spike_consumer_effects')
                ->where('consumer_name', 'spike.event_counter')
                ->value('effect_count'),
        );
    }

    public function test_pending_outbox_message_survives_process_restart(): void
    {
        $handler = app(CreateWorkspaceHandler::class);
        $result = $handler->handle(new CreateWorkspaceCommand(
            name: 'Restart Test',
            requestedByUserId: '00000000-0000-4000-8000-000000000099',
        ));

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $result->eventId,
            'dispatched_at' => null,
        ]);

        app(OutboxProcessor::class)->processPending();

        $this->assertDatabaseMissing('platform.outbox_messages', [
            'event_id' => $result->eventId,
            'dispatched_at' => null,
        ]);
    }
}
