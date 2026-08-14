<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Platform\Messaging\Infrastructure\OutboxDeadLetterManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RetryOutboxCommand extends Command
{
    protected $signature = 'atlas:outbox:retry {event-id : Event UUID to release from dead-letter}';

    protected $description = 'Release a dead-lettered outbox message for a new delivery cycle';

    public function handle(OutboxDeadLetterManager $deadLetters): int
    {
        $eventId = (string) $this->argument('event-id');

        if (! Str::isUuid($eventId)) {
            $this->error('The event-id argument must be a valid UUID.');

            return self::INVALID;
        }

        if (! $deadLetters->retry($eventId)) {
            $this->error("No dead-lettered outbox message found for event {$eventId}.");

            return self::FAILURE;
        }

        $this->info("Outbox message {$eventId} scheduled for retry.");

        return self::SUCCESS;
    }
}
