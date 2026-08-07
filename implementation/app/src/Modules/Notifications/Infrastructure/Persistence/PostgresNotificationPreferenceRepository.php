<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Infrastructure\Persistence;

use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Illuminate\Support\Facades\DB;

final class PostgresNotificationPreferenceRepository
{
    /** @return array{in_app_mode: string, email_mode: string, revision: int} */
    public function findOrDefault(string $workspaceId, string $userId): array
    {
        $row = DB::table('notifications.preferences')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->first();

        if ($row === null) {
            return [
                'in_app_mode' => NotificationPolicy::IN_APP_ENABLED,
                'email_mode' => NotificationPolicy::EMAIL_DISABLED,
                'revision' => 0,
            ];
        }

        return [
            'in_app_mode' => $row->in_app_mode,
            'email_mode' => $row->email_mode,
            'revision' => (int) $row->revision,
        ];
    }

    public function upsert(
        string $workspaceId,
        string $userId,
        string $inAppMode,
        string $emailMode,
        int $revision,
        \DateTimeImmutable $updatedAt,
    ): int {
        $existing = DB::table('notifications.preferences')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->first();

        $nextRevision = $existing !== null ? ((int) $existing->revision) + 1 : 1;

        if ($existing === null) {
            DB::table('notifications.preferences')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'in_app_mode' => $inAppMode,
                'email_mode' => $emailMode,
                'revision' => $nextRevision,
                'updated_at' => $updatedAt->format('Y-m-d H:i:sP'),
            ]);
        } else {
            if ($revision !== (int) $existing->revision) {
                throw new \DomainException('Revision conflict.');
            }

            DB::table('notifications.preferences')
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $userId)
                ->update([
                    'in_app_mode' => $inAppMode,
                    'email_mode' => $emailMode,
                    'revision' => $nextRevision,
                    'updated_at' => $updatedAt->format('Y-m-d H:i:sP'),
                ]);
        }

        return $nextRevision;
    }
}
