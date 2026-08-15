<?php

declare(strict_types=1);

namespace Atlas\Composition\Notifications;

use Atlas\Modules\Notifications\Application\ProcessAdvisorNotificationSignalHandler;
use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;

final class OutboxNotificationsProcessConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly ProcessAdvisorNotificationSignalHandler $process,
    ) {}

    public function name(): string
    {
        return 'composition.notifications_process';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'advisor.overview_changed') {
            return;
        }

        $payload = $message->payload;
        $workspaceId = $payload['workspace_id'];
        $overviewVersion = (int) $payload['advisor_overview_version'];
        $sourceEventId = $message->eventId->value;
        $requestId = hash('sha256', $sourceEventId.'|'.NotificationPolicy::VERSION);

        $this->process->handle(
            workspaceId: $workspaceId,
            advisorOverviewVersion: $overviewVersion,
            sourceEventId: $sourceEventId,
            requestId: $requestId,
        );
    }
}
