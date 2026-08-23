<?php

declare(strict_types=1);

namespace Atlas\Composition\Subscriptions;

use Atlas\Modules\Subscriptions\Application\StartTrialForWorkspaceHandler;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class StartWorkspaceTrialConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly StartTrialForWorkspaceHandler $startTrial,
    ) {}

    public function name(): string
    {
        return 'composition.subscriptions.start_workspace_trial';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'workspace.workspace_activated') {
            return;
        }

        $workspaceId = trim((string) ($message->payload['workspace_id'] ?? ''));
        if ($workspaceId === '') {
            throw new \RuntimeException('Workspace activation payload is incomplete.');
        }

        $this->startTrial->handle(
            workspaceId: $workspaceId,
            activatedAt: $message->occurredAt,
            correlationId: $message->correlationId,
            causationId: $message->eventId->value,
        );
    }
}
