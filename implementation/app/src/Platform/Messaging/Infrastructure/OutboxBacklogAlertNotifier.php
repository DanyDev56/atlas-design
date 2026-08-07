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
        $url = config('platform.outbox.alert_webhook_url');

        if (! is_string($url) || $url === '') {
            return;
        }

        try {
            Http::timeout(3)->post($url, [
                'alert' => 'outbox_backlog_above_threshold',
                'context' => $context,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Outbox alert webhook failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
