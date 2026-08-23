<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Atlas\Modules\Subscriptions\Application\ProcessRecurringBillingWebhookHandler;
use Illuminate\Console\Command;

final class ReplaySubscriptionWebhookCommand extends Command
{
    protected $signature = 'atlas:subscriptions:replay-webhook
        {provider : Billing provider key}
        {event-id : Provider event identifier}';

    protected $description = 'Replay one previously verified subscription webhook from the inbox';

    public function handle(ProcessRecurringBillingWebhookHandler $handler): int
    {
        try {
            $result = $handler->replay(
                (string) $this->argument('provider'),
                (string) $this->argument('event-id'),
            );
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Webhook %s is %s.',
            $result['provider_event_id'],
            $result['status'],
        ));

        return self::SUCCESS;
    }
}
