<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingReconciliationGateway;
use Atlas\Modules\Subscriptions\Domain\Entitlement;
use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;
use Atlas\Modules\Subscriptions\Domain\Plan;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionEntitlementPolicy;
use Atlas\Modules\Subscriptions\Domain\SubscriptionReconciliationException;
use Atlas\Modules\Subscriptions\Domain\SubscriptionRepository;

final class ReconcileSubscriptionHandler
{
    private const array RESTRICTED_CAPABILITIES = ['workspace.read', 'data.export', 'subscription.manage'];

    public function __construct(
        private readonly RecurringBillingReconciliationGateway $gateway,
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanCatalogRepository $catalog,
        private readonly EntitlementRepository $entitlements,
        private readonly SubscriptionEntitlementPolicy $policy,
    ) {}

    /** @return array{subscription: Subscription, provider: ProviderSubscriptionState, changes: list<array{field:string,current:mixed,provider:mixed}>} */
    public function inspect(string $subscriptionId, int $expectedVersion): array
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null) {
            throw new SubscriptionReconciliationException('SubscriptionNotFound', 'Abonnement introuvable.', 404);
        }
        if ($subscription->version() !== $expectedVersion) {
            throw new SubscriptionReconciliationException('SubscriptionVersionConflict', 'L’abonnement a changé depuis le chargement de la page.', 409);
        }
        $provider = $this->gateway->inspect($subscription);

        return ['subscription' => $subscription, 'provider' => $provider, 'changes' => $this->changes($subscription, $provider)];
    }

    /** @return array{subscription: Subscription, changes: list<array{field:string,current:mixed,provider:mixed}>} */
    public function apply(
        string $subscriptionId,
        int $expectedVersion,
        ProviderSubscriptionState $provider,
        bool $recordVerifiedObservation = false,
    ): array
    {
        $subscription = $this->subscriptions->findByIdForUpdate($subscriptionId);
        if ($subscription === null) {
            throw new SubscriptionReconciliationException('SubscriptionNotFound', 'Abonnement introuvable.', 404);
        }
        if ($subscription->version() !== $expectedVersion) {
            throw new SubscriptionReconciliationException('SubscriptionVersionConflict', 'L’abonnement a changé depuis la prévisualisation.', 409);
        }
        $changes = $this->changes($subscription, $provider);
        if ($changes === [] && ! $recordVerifiedObservation) {
            throw new SubscriptionReconciliationException('SubscriptionAlreadyAligned', 'Atlas et Stripe sont déjà alignés.', 409);
        }
        $plan = $this->plan($subscription);
        try {
            $subscription->reconcile($provider);
        } catch (\DomainException $exception) {
            throw new SubscriptionReconciliationException('SubscriptionReconciliationConflict', 'La réconciliation ne peut plus être appliquée.', 409, $exception);
        }
        $this->subscriptions->save($subscription);
        $previous = $this->entitlements->findByWorkspaceId($subscription->workspaceId());
        $this->entitlements->save(new Entitlement(
            workspaceId: $subscription->workspaceId(),
            sourceType: 'Subscription',
            sourceId: $subscription->id()->value,
            fullCapabilities: $plan->capabilities,
            restrictedCapabilities: self::RESTRICTED_CAPABILITIES,
            limits: $plan->limits,
            validUntil: $this->policy->validUntil($subscription),
            computedAt: $provider->observedAt,
            version: ($previous?->version ?? 0) + 1,
        ));

        return ['subscription' => $subscription, 'changes' => $changes];
    }

    /** @return list<array{field:string,current:mixed,provider:mixed}> */
    private function changes(Subscription $subscription, ProviderSubscriptionState $provider): array
    {
        $values = [
            'status' => [$subscription->status(), $provider->status],
            'current_period_start' => [$subscription->currentPeriodStart()->format(DATE_ATOM), $provider->currentPeriodStart->format(DATE_ATOM)],
            'current_period_end' => [$subscription->currentPeriodEnd()->format(DATE_ATOM), $provider->currentPeriodEnd->format(DATE_ATOM)],
            'cancel_at_period_end' => [$subscription->cancelAtPeriodEnd(), $provider->cancelAtPeriodEnd || $provider->status === Subscription::STATUS_CANCELED],
            'canceled_at' => [$subscription->canceledAt()?->format(DATE_ATOM), $provider->status === Subscription::STATUS_CANCELED ? $provider->canceledAt?->format(DATE_ATOM) : null],
        ];
        $changes = [];
        foreach ($values as $field => [$current, $remote]) {
            if ($current !== $remote) {
                $changes[] = ['field' => $field, 'current' => $current, 'provider' => $remote];
            }
        }

        return $changes;
    }

    private function plan(Subscription $subscription): Plan
    {
        $plan = $this->catalog->findVersion((string) config('subscriptions.default_plan.code', 'atlas_solo'), (int) config('subscriptions.default_plan.version', 1));
        foreach ($plan?->prices ?? [] as $price) {
            if ($price->id === $subscription->planPriceId()) {
                return $plan;
            }
        }
        throw new SubscriptionReconciliationException('SubscriptionPlanUnavailable', 'Le plan local de cet abonnement est indisponible.', 409);
    }
}
