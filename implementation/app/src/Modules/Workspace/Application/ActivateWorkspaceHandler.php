<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Contracts\ActivateWorkspaceCommand;
use Atlas\Modules\Workspace\Contracts\ActivateWorkspaceResult;
use Atlas\Modules\Workspace\Domain\Workspace;
use Atlas\Modules\Workspace\Domain\WorkspaceActivated;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class ActivateWorkspaceHandler
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(ActivateWorkspaceCommand $command): ActivateWorkspaceResult
    {
        if (! $command->hasActiveOwner) {
            throw new \DomainException('Owner readiness proof requires an active owner.');
        }

        return DB::transaction(function () use ($command): ActivateWorkspaceResult {
            $workspace = $this->workspaces->findById(new WorkspaceId($command->workspaceId));

            if ($workspace === null) {
                throw new \DomainException('Workspace not found.');
            }

            if ($workspace->status() === Workspace::STATUS_ACTIVE) {
                throw new \DomainException('Workspace is already active.');
            }

            if ($workspace->version() !== $command->expectedRevision) {
                throw new \DomainException('Workspace version conflict.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $workspace->activate($now);

            $event = new WorkspaceActivated(
                workspaceId: $workspace->id(),
                accessState: $workspace->accessState(),
                governanceVersion: $workspace->governanceVersion(),
                eventId: EventId::generate(),
                occurredAt: $now,
            );

            $this->workspaces->save($workspace);
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $event,
                correlationId: $command->correlationId,
            ));

            return new ActivateWorkspaceResult(
                workspaceId: $workspace->id()->value,
                status: $workspace->status(),
                accessState: $workspace->accessState(),
                governanceVersion: $workspace->governanceVersion(),
                eventId: $event->eventId()->value,
            );
        });
    }
}
