<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

interface RecurringBillingGateway
{
    public function createCheckoutSession(
        string $workspaceId,
        string $planPriceId,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession;

    public function createPortalSession(
        string $workspaceId,
        string $providerSubscriptionReference,
        string $returnUrl,
    ): PortalSession;
}
