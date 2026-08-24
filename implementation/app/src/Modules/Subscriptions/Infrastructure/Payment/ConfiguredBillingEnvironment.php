<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

final class ConfiguredBillingEnvironment
{
    public function current(): string
    {
        $gateway = strtolower((string) config('subscriptions.gateway', 'fake'));
        if ($gateway === 'fake') {
            return 'Sandbox';
        }

        if ($gateway !== 'stripe') {
            return 'Unknown';
        }

        $secretKey = trim((string) config('subscriptions.stripe.secret_key', ''));

        return match (true) {
            str_starts_with($secretKey, 'sk_test_') => 'Sandbox',
            str_starts_with($secretKey, 'sk_live_') => 'Live',
            default => 'Unknown',
        };
    }
}
