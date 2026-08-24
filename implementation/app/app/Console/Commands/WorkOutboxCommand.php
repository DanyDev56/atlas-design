<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperationsRuntimeRecorder;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Console\Command;

final class WorkOutboxCommand extends Command
{
    protected $signature = 'atlas:outbox:work
        {--batch=100 : Maximum number of messages processed per cycle}
        {--sleep=2 : Seconds to wait when no message is pending}
        {--max-cycles=0 : Stop after this many cycles; zero keeps the worker running}';

    protected $description = 'Continuously process pending outbox messages';

    private bool $shouldQuit = false;

    public function handle(OutboxProcessor $processor, PostgresOperationsRuntimeRecorder $runtime): int
    {
        $batch = (int) $this->option('batch');
        $sleepSeconds = (int) $this->option('sleep');
        $maxCycles = (int) $this->option('max-cycles');

        if ($batch < 1 || $sleepSeconds < 0 || $maxCycles < 0) {
            $this->error('Options must satisfy: batch >= 1, sleep >= 0 and max-cycles >= 0.');

            return self::INVALID;
        }

        $this->shouldQuit = false;
        $this->trap(
            fn (): array => [SIGINT, SIGTERM, SIGQUIT],
            function (): void {
                $this->shouldQuit = true;
            },
        );

        $cycles = 0;
        $processed = 0;
        $this->info('Outbox worker started.');

        while (! $this->shouldQuit) {
            $runtime->heartbeat('worker');
            $cycleCount = $processor->processPending($batch);
            $processed += $cycleCount;
            $cycles++;

            if ($maxCycles > 0 && $cycles >= $maxCycles) {
                break;
            }

            if ($cycleCount === 0 && $sleepSeconds > 0 && ! $this->shouldQuit) {
                sleep($sleepSeconds);
            }
        }

        $this->info("Outbox worker stopped after {$cycles} cycle(s); processed {$processed} message(s).");

        return self::SUCCESS;
    }
}
