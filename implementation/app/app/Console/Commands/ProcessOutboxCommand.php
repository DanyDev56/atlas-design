<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Console\Command;

final class ProcessOutboxCommand extends Command
{
    protected $signature = 'atlas:outbox:process {--batch=100}';

    protected $description = 'Process pending outbox messages';

    public function handle(OutboxProcessor $processor): int
    {
        $count = $processor->processPending((int) $this->option('batch'));
        $this->info("Processed {$count} outbox message(s).");

        return self::SUCCESS;
    }
}
