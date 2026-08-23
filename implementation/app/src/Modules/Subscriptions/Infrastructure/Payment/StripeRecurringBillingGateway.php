<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\CheckoutSession;
use Atlas\Modules\Subscriptions\Contracts\PortalSession;
use Atlas\Modules\Subscriptions\Contracts\RecurringBillingGateway;
use Atlas\Modules\Subscriptions\Contracts\StripeBillingClient;

final readonly class StripeRecurringBillingGateway implements RecurringBillingGateway
{
    /** @param array<string, string> $priceIds */
    public function __construct(
        private StripeBillingClient $client,
        private array $priceIds,
    ) {}

    public function createCheckoutSession(
        string $workspaceId,
        string $planPriceId,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $stripePriceId = trim((string) ($this->priceIds[$planPriceId] ?? ''));
        if (! str_starts_with($stripePriceId, 'price_')) {
            throw new \DomainException('Stripe plan price unavailable.');
        }

        $session = $this->client->createCheckoutSession([
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $stripePriceId,
                'quantity' => 1,
            ]],
            'client_reference_id' => $workspaceId,
            'metadata' => [
                'workspace_id' => $workspaceId,
                'plan_price_id' => $planPriceId,
            ],
            'subscription_data' => [
                'metadata' => [
                    'workspace_id' => $workspaceId,
                    'plan_price_id' => $planPriceId,
                ],
            ],
            'billing_address_collection' => 'required',
            'tax_id_collection' => ['enabled' => true],
            'success_url' => $this->withQuery($successUrl, [
                'checkout' => 'pending',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ]),
            'cancel_url' => $this->withQuery($cancelUrl, ['checkout' => 'canceled']),
        ], 'atlas_checkout_'.hash('sha256', $workspaceId.'|'.$idempotencyKey));

        $id = trim((string) ($session['id'] ?? ''));
        $url = trim((string) ($session['url'] ?? ''));
        if (! str_starts_with($id, 'cs_') || ! str_starts_with($url, 'https://')) {
            throw new \DomainException('Stripe checkout response invalid.');
        }

        return new CheckoutSession($id, $url, 'stripe');
    }

    public function createPortalSession(
        string $workspaceId,
        string $providerSubscriptionReference,
        string $returnUrl,
    ): PortalSession {
        $subscription = $this->client->retrieveSubscription($providerSubscriptionReference);
        $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
        if (($metadata['workspace_id'] ?? null) !== $workspaceId) {
            throw new \DomainException('Stripe subscription workspace mismatch.');
        }

        $customer = $subscription['customer'] ?? null;
        $customerId = is_array($customer) ? ($customer['id'] ?? null) : $customer;
        if (! is_string($customerId) || ! str_starts_with($customerId, 'cus_')) {
            throw new \DomainException('Stripe customer unavailable.');
        }

        $session = $this->client->createPortalSession([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
        $id = trim((string) ($session['id'] ?? ''));
        $url = trim((string) ($session['url'] ?? ''));
        if (! str_starts_with($id, 'bps_') || ! str_starts_with($url, 'https://')) {
            throw new \DomainException('Stripe portal response invalid.');
        }

        return new PortalSession($id, $url, 'stripe');
    }

    /** @param array<string, string> $parameters */
    private function withQuery(string $url, array $parameters): string
    {
        $result = $url.(str_contains($url, '?') ? '&' : '?').http_build_query(
            $parameters,
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $result);
    }
}
