<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Domain\Trial;
use Atlas\Modules\Subscriptions\Domain\TrialRepository;
use Illuminate\Support\Facades\DB;

final class PostgresTrialRepository implements TrialRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Trial
    {
        $row = DB::table('subscriptions.trials')
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row !== null ? Trial::reconstitute((array) $row) : null;
    }

    public function save(Trial $trial): void
    {
        DB::table('subscriptions.trials')->insert([
            'id' => $trial->id()->value,
            'workspace_id' => $trial->workspaceId(),
            'plan_id' => $trial->planId(),
            'status' => $trial->status(),
            'started_at' => $trial->startedAt()->format('Y-m-d H:i:sP'),
            'ends_at' => $trial->endsAt()->format('Y-m-d H:i:sP'),
            'version' => $trial->version(),
            'created_at' => $trial->startedAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $trial->startedAt()->format('Y-m-d H:i:sP'),
        ]);
    }
}
