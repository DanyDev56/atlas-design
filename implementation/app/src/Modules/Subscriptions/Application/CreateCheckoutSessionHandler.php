<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingGateway;
use Atlas\Modules\Subscriptions\Domain\BillingInterval;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Infrastructure\PostgresSubscriptionsIdempotencyStore;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class CreateCheckoutSessionHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PlanCatalogRepository $catalog,
        private readonly RecurringBillingGateway $gateway,
        private readonly PostgresSubscriptionsIdempotencyStore $idempotency,
    ) {}

    /** @return array<string, string> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $billingInterval,
        string $idempotencyKey,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'subscriptions.manage');

        if (! config('subscriptions.checkout_enabled', false)) {
            throw new \DomainException('Checkout unavailable.');
        }

        $scope = 'subscriptions.checkout.'.$workspaceId;
        $fingerprint = hash('sha256', $billingInterval);
        $cached = $this->idempotency->find($scope, $idempotencyKey);
        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        $plan = $this->catalog->findVersion(
            (string) config('subscriptions.default_plan.code', 'atlas_solo'),
            (int) config('subscriptions.default_plan.version', 1),
        );
        $interval = BillingInterval::tryFrom($billingInterval);
        $price = $interval !== null ? $plan?->priceFor($interval) : null;
        if ($plan === null || $price === null || $price->status !== 'Candidate') {
            throw new \DomainException('Plan price unavailable.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $session = $this->gateway->createCheckoutSession(
            workspaceId: $workspaceId,
            planPriceId: $price->id,
            idempotencyKey: $idempotencyKey,
            successUrl: $baseUrl.'/app/settings/subscription',
            cancelUrl: $baseUrl.'/app/settings/subscription',
        );

        $response = [
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
            'provider' => $session->provider,
            'mode' => 'Preview',
        ];
        $this->idempotency->store($scope, $idempotencyKey, $fingerprint, $response);

        return $response;
    }
}
