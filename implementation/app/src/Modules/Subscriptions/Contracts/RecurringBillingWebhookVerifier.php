<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;

interface RecurringBillingWebhookVerifier
{
    public function verify(string $provider, string $payload, string $signature): VerifiedRecurringBillingEvent;

    public function decodeTrusted(string $provider, string $payload): VerifiedRecurringBillingEvent;
}
