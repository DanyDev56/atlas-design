<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\CheckoutSession;
use Atlas\Modules\Subscriptions\Contracts\PortalSession;
use Atlas\Modules\Subscriptions\Contracts\RecurringBillingGateway;

final class FakeRecurringBillingGateway implements RecurringBillingGateway
{
    public function createCheckoutSession(
        string $workspaceId,
        string $planPriceId,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $sessionId = 'fake_'.substr(hash('sha256', implode('|', [
            $workspaceId,
            $planPriceId,
            $idempotencyKey,
            $successUrl,
            $cancelUrl,
        ])), 0, 24);
        $separator = str_contains($successUrl, '?') ? '&' : '?';

        return new CheckoutSession(
            id: $sessionId,
            url: $successUrl.$separator.http_build_query([
                'checkout' => 'preview',
                'session_id' => $sessionId,
            ], '', '&', PHP_QUERY_RFC3986),
            provider: 'fake',
        );
    }

    public function createPortalSession(
        string $workspaceId,
        string $providerSubscriptionReference,
        string $returnUrl,
    ): PortalSession {
        return new PortalSession(
            id: 'fake_portal_'.substr(hash('sha256', $workspaceId.'|'.$providerSubscriptionReference), 0, 24),
            url: $returnUrl,
            provider: 'fake',
        );
    }
}
