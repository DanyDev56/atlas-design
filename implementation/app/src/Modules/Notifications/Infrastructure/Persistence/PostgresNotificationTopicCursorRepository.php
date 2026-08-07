<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresNotificationTopicCursorRepository
{
    public function lastOverviewVersion(string $workspaceId, string $topic): int
    {
        $row = DB::table('notifications.topic_cursors')
            ->where('workspace_id', $workspaceId)
            ->where('notification_topic', $topic)
            ->first();

        return $row !== null ? (int) $row->last_advisor_overview_version : 0;
    }

    public function advance(string $workspaceId, string $topic, int $overviewVersion): void
    {
        $existing = DB::table('notifications.topic_cursors')
            ->where('workspace_id', $workspaceId)
            ->where('notification_topic', $topic)
            ->first();

        if ($existing === null) {
            DB::table('notifications.topic_cursors')->insert([
                'workspace_id' => $workspaceId,
                'notification_topic' => $topic,
                'last_advisor_overview_version' => $overviewVersion,
            ]);

            return;
        }

        DB::table('notifications.topic_cursors')
            ->where('workspace_id', $workspaceId)
            ->where('notification_topic', $topic)
            ->update(['last_advisor_overview_version' => $overviewVersion]);
    }
}
