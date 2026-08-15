<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Atlas\Modules\Crm\Domain\Activity;
use Atlas\Modules\Crm\Domain\ActivityId;
use Atlas\Modules\Crm\Domain\ClientId;
use Illuminate\Support\Facades\DB;

final class PostgresActivityRepository
{
    public function insert(Activity $activity): void
    {
        DB::table('crm.activities')->insert([
            'id' => $activity->id()->value,
            'workspace_id' => $activity->workspaceId(),
            'client_id' => $activity->clientId()->value,
            'contact_id' => $activity->contactId(),
            'opportunity_id' => $activity->opportunityId(),
            'kind' => $activity->kind(),
            'summary' => $activity->summary(),
            'occurred_at' => $activity->occurredAt()->format('Y-m-d H:i:sP'),
            'status' => $activity->status(),
            'version' => $activity->version(),
            'created_at' => $activity->createdAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $activity->updatedAt()->format('Y-m-d H:i:sP'),
        ]);
    }

    public function findById(string $workspaceId, ActivityId $id): ?Activity
    {
        $row = DB::table('crm.activities')
            ->where('workspace_id', $workspaceId)
            ->where('id', $id->value)
            ->first();

        return $row !== null ? Activity::reconstitute((array) $row) : null;
    }

    /** @param array{kind: string, summary: string, occurred_at: \DateTimeImmutable, version: int} $previous */
    public function updateWithRevision(
        Activity $activity,
        array $previous,
        string $correctionReason,
        string $correctedBy,
        \DateTimeImmutable $correctedAt,
    ): void {
        DB::table('crm.activity_revisions')->insert([
            'activity_id' => $activity->id()->value,
            'workspace_id' => $activity->workspaceId(),
            'revision' => $previous['version'],
            'kind' => $previous['kind'],
            'summary' => $previous['summary'],
            'occurred_at' => $previous['occurred_at']->format('Y-m-d H:i:sP'),
            'correction_reason' => $correctionReason,
            'corrected_by' => $correctedBy,
            'corrected_at' => $correctedAt->format('Y-m-d H:i:sP'),
        ]);
        DB::table('crm.activities')
            ->where('workspace_id', $activity->workspaceId())
            ->where('id', $activity->id()->value)
            ->update([
                'kind' => $activity->kind(),
                'summary' => $activity->summary(),
                'occurred_at' => $activity->occurredAt()->format('Y-m-d H:i:sP'),
                'version' => $activity->version(),
                'updated_at' => $activity->updatedAt()->format('Y-m-d H:i:sP'),
            ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRecordedByClient(string $workspaceId, ClientId $clientId): array
    {
        return DB::table('crm.activities')
            ->where('workspace_id', $workspaceId)
            ->where('client_id', $clientId->value)
            ->where('status', Activity::STATUS_RECORDED)
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
