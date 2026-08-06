<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Contracts\CreateWorkspaceCommand;
use Atlas\Modules\Workspace\Contracts\CreateWorkspaceResult;
use Atlas\Modules\Workspace\Domain\Workspace;
use Atlas\Modules\Workspace\Domain\WorkspaceCreated;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Illuminate\Support\Facades\DB;

final class CreateWorkspaceHandler
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(CreateWorkspaceCommand $command): CreateWorkspaceResult
    {
        return DB::transaction(function () use ($command): CreateWorkspaceResult {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $workspaceId = $command->workspaceId !== null
                ? new WorkspaceId($command->workspaceId)
                : WorkspaceId::generate();

            $workspace = Workspace::create(
                id: $workspaceId,
                name: $command->name,
                requestedByUserId: $command->requestedByUserId,
                now: $now,
            );

            $event = WorkspaceCreated::fromWorkspace(
                workspace: $workspace,
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->workspaces->save($workspace);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $event,
                correlationId: $command->correlationId,
            ));

            return new CreateWorkspaceResult(
                workspaceId: $workspace->id()->value,
                status: $workspace->status(),
                accessState: $workspace->accessState(),
                eventId: $event->eventId()->value,
            );
        });
    }
}
