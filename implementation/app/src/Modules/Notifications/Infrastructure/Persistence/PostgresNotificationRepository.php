<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Infrastructure\Persistence;

use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresNotificationRepository
{
    /** @param list<string> $channels @param array<string, mixed> $content */
    public function create(
        string $workspaceId,
        string $recipientUserId,
        ?string $recommendationId,
        string $priority,
        array $content,
        array $channels,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $displayUntil,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('notifications.notifications')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'recipient_user_id' => $recipientUserId,
            'recommendation_id' => $recommendationId,
            'notification_topic' => NotificationPolicy::TOPIC_ADVISOR_PRIORITY,
            'status' => NotificationPolicy::STATUS_ACTIVE,
            'read_state' => NotificationPolicy::READ_UNREAD,
            'priority' => $priority,
            'content' => json_encode($content, JSON_THROW_ON_ERROR),
            'selected_channels' => json_encode($channels, JSON_THROW_ON_ERROR),
            'revision' => 1,
            'created_at' => $createdAt->format('Y-m-d H:i:sP'),
            'display_until' => $displayUntil?->format('Y-m-d H:i:sP'),
        ]);

        return $id;
    }

    public function resolveActiveForRecipient(string $workspaceId, string $recipientUserId): void
    {
        DB::table('notifications.notifications')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $recipientUserId)
            ->where('status', NotificationPolicy::STATUS_ACTIVE)
            ->update(['status' => NotificationPolicy::STATUS_RESOLVED]);
    }

    public function findById(string $workspaceId, string $recipientUserId, string $notificationId): ?array
    {
        $row = DB::table('notifications.notifications')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $recipientUserId)
            ->where('id', $notificationId)
            ->first();

        return $row !== null ? $this->mapRow((array) $row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForRecipient(string $workspaceId, string $recipientUserId, ?string $status = null): array
    {
        $query = DB::table('notifications.notifications')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $recipientUserId)
            ->whereJsonContains('selected_channels', NotificationPolicy::CHANNEL_IN_APP)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn ($row) => $this->mapRow((array) $row))->all();
    }

    public function countUnread(string $workspaceId, string $recipientUserId): int
    {
        return (int) DB::table('notifications.notifications')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $recipientUserId)
            ->where('status', NotificationPolicy::STATUS_ACTIVE)
            ->where('read_state', NotificationPolicy::READ_UNREAD)
            ->whereJsonContains('selected_channels', NotificationPolicy::CHANNEL_IN_APP)
            ->count();
    }

    public function markRead(
        string $workspaceId,
        string $recipientUserId,
        string $notificationId,
        int $expectedRevision,
    ): int {
        $row = DB::table('notifications.notifications')
            ->where('workspace_id', $workspaceId)
            ->where('recipient_user_id', $recipientUserId)
            ->where('id', $notificationId)
            ->first();

        if ($row === null) {
            throw new \DomainException('Notification not found.');
        }

        if ((int) $row->revision !== $expectedRevision) {
            throw new \DomainException('Revision conflict.');
        }

        $nextRevision = (int) $row->revision + 1;

        DB::table('notifications.notifications')
            ->where('id', $notificationId)
            ->update([
                'read_state' => NotificationPolicy::READ_READ,
                'revision' => $nextRevision,
            ]);

        return $nextRevision;
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): array
    {
        return [
            'notification_id' => $row['id'],
            'workspace_id' => $row['workspace_id'],
            'recommendation_id' => $row['recommendation_id'],
            'notification_topic' => $row['notification_topic'],
            'status' => $row['status'],
            'read_state' => $row['read_state'],
            'priority' => $row['priority'],
            'content' => json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR),
            'selected_channels' => json_decode($row['selected_channels'], true, 512, JSON_THROW_ON_ERROR),
            'revision' => (int) $row['revision'],
            'created_at' => $row['created_at'],
            'display_until' => $row['display_until'],
        ];
    }
}
