<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Subscriptions\Application\StartTrialForWorkspaceHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BackfillWorkspaceTrialsCommand extends Command
{
    protected $signature = 'atlas:subscriptions:backfill-trials
        {--dry-run : Count eligible workspaces without starting trials}';

    protected $description = 'Start a fresh trial for active workspaces that predate the Subscriptions context';

    public function handle(StartTrialForWorkspaceHandler $handler): int
    {
        $workspaceIds = DB::table('workspace.workspaces as workspace')
            ->where('workspace.status', 'Active')
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('subscriptions.trials as trial')
                    ->whereColumn('trial.workspace_id', 'workspace.id');
            })
            ->orderBy('workspace.id')
            ->pluck('workspace.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d active workspace(s) eligible for a fresh trial.', count($workspaceIds)));

            return self::SUCCESS;
        }

        $startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($workspaceIds as $workspaceId) {
            $handler->handle($workspaceId, $startedAt);
        }

        $this->info(sprintf('Started %d workspace trial(s).', count($workspaceIds)));

        return self::SUCCESS;
    }
}
