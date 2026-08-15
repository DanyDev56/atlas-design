<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Application;

use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class MarkNotificationReadHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresNotificationRepository $notifications,
    ) {}

    /** @return array{notification_id: string, revision: int} */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $notificationId,
        int $expectedRevision,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.inbox.mark-read');

        $revision = $this->notifications->markRead(
            $workspaceId,
            $actorUserId,
            $notificationId,
            $expectedRevision,
        );

        return [
            'notification_id' => $notificationId,
            'revision' => $revision,
        ];
    }
}
