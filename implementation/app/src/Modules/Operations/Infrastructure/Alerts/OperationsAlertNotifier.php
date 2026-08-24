<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Alerts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OperationsAlertNotifier
{
    /** @param array<string, int|float|string|bool|null> $context */
    public function notify(string $alert, string $state, array $context): bool
    {
        $url = config('operations.alerts.webhook_url');
        if (! is_string($url) || $url === '') {
            return false;
        }

        try {
            Http::timeout(3)->post($url, [
                'alert' => $alert,
                'state' => $state,
                'environment' => (string) config('app.env'),
                'context' => $context,
            ])->throw();

            return true;
        } catch (\Throwable $exception) {
            Log::error('Operations alert webhook failed', [
                'alert' => $alert,
                'state' => $state,
                'exception_type' => get_debug_type($exception),
            ]);

            return false;
        }
    }
}
