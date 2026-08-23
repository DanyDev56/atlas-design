<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

final readonly class OfficialStripeBillingClient implements StripeBillingClient
{
    public function __construct(private StripeClient $client) {}

    public function createCheckoutSession(array $parameters, string $idempotencyKey): array
    {
        try {
            return $this->client->checkout->sessions
                ->create($parameters, ['idempotency_key' => $idempotencyKey])
                ->toArray();
        } catch (ApiErrorException $exception) {
            throw new \DomainException('Stripe billing unavailable.', previous: $exception);
        }
    }

    public function createPortalSession(array $parameters): array
    {
        try {
            return $this->client->billingPortal->sessions->create($parameters)->toArray();
        } catch (ApiErrorException $exception) {
            throw new \DomainException('Stripe billing unavailable.', previous: $exception);
        }
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            return $this->client->subscriptions->retrieve($subscriptionId, [])->toArray();
        } catch (ApiErrorException $exception) {
            throw new \DomainException('Stripe billing unavailable.', previous: $exception);
        }
    }
}
