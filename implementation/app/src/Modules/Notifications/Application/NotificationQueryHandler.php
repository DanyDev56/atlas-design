<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Application;

use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class NotificationQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresNotificationRepository $notifications,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listNotifications(string $actorUserId, string $workspaceId, ?string $status = null): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.inbox.read');

        return $this->notifications->listForRecipient($workspaceId, $actorUserId, $status);
    }

    /** @return array<string, mixed> */
    public function getNotification(string $actorUserId, string $workspaceId, string $notificationId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.inbox.read');

        $notification = $this->notifications->findById($workspaceId, $actorUserId, $notificationId);

        if ($notification === null) {
            throw new \DomainException('Notification not found.');
        }

        return $notification;
    }

    /** @return array{unread_count: int} */
    public function getUnreadCount(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.inbox.read');

        return [
            'unread_count' => $this->notifications->countUnread($workspaceId, $actorUserId),
        ];
    }
}
