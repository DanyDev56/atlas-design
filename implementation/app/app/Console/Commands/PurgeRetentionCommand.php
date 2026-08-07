<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Platform\Retention\Infrastructure\RetentionPurger;
use Illuminate\Console\Command;

final class PurgeRetentionCommand extends Command
{
    protected $signature = 'atlas:retention:purge {--dry-run : Report counts without deleting}';

    protected $description = 'Purge expired sessions, old dispatched outbox messages, and stale idempotency keys';

    public function handle(RetentionPurger $purger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $purger->purgeAll($dryRun);

        $mode = $dryRun ? 'Would purge' : 'Purged';
        $this->info("{$mode} {$result->sessions} session(s).");
        $this->info("{$mode} {$result->outboxDispatched} dispatched outbox message(s).");
        $this->info("{$mode} {$result->idempotencyKeys} idempotency key(s).");

        return self::SUCCESS;
    }
}
