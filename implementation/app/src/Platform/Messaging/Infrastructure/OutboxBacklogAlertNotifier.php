<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Infrastructure;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OutboxBacklogAlertNotifier
{
    /** @param array<string, mixed> $context */
    public function notifyWarning(array $context): void
    {
        $this->notify('outbox_backlog_above_threshold', $context);
    }

    /** @param array<string, mixed> $context */
    public function notifyDeadLetter(array $context): void
    {
        $this->notify('outbox_message_dead_lettered', $context);
    }

    /** @param array<string, mixed> $context */
    private function notify(string $alert, array $context): void
    {
        $url = config('platform.outbox.alert_webhook_url');

        if (! is_string($url) || $url === '') {
            return;
        }

        try {
            Http::timeout(3)->post($url, [
                'alert' => $alert,
                'context' => $context,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Outbox alert webhook failed', [
                'exception_type' => get_debug_type($exception),
            ]);
        }
    }
}
