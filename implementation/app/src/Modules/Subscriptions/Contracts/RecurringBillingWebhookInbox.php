<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;

interface RecurringBillingWebhookInbox
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, inserted: bool}
     */
    public function record(VerifiedRecurringBillingEvent $event, string $payloadHash, array $payload): array;

    /** @return array<string, mixed>|null */
    public function find(string $provider, string $providerEventId): ?array;

    public function claim(string $provider, string $providerEventId): bool;

    public function mark(string $provider, string $providerEventId, string $status, ?string $reason = null): void;
}
