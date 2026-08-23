<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

interface StripeBillingClient
{
    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $parameters, string $idempotencyKey): array;

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function createPortalSession(array $parameters): array;

    /** @return array<string, mixed> */
    public function retrieveSubscription(string $subscriptionId): array;
}
