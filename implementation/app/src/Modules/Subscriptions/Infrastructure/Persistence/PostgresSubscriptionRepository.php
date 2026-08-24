<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionRepository;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\ConfiguredBillingEnvironment;
use Illuminate\Support\Facades\DB;

final class PostgresSubscriptionRepository implements SubscriptionRepository
{
    public function __construct(
        private readonly ConfiguredBillingEnvironment $environment,
    ) {}

    public function findByWorkspaceId(string $workspaceId): ?Subscription
    {
        $row = DB::table('subscriptions.recurring_subscriptions')->where('workspace_id', $workspaceId)->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    public function lockWorkspace(string $workspaceId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', ['subscription-workspace', $workspaceId]);
    }

    public function findByWorkspaceIdForUpdate(string $workspaceId): ?Subscription
    {
        $row = DB::table('subscriptions.recurring_subscriptions')
            ->where('workspace_id', $workspaceId)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    public function lockProviderReference(string $provider, string $providerReference): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$provider, $providerReference]);
    }

    public function findByProviderReference(string $provider, string $providerReference): ?Subscription
    {
        $subscriptionId = $this->findSubscriptionIdForProviderReference($provider, $providerReference);
        if ($subscriptionId === null) {
            return null;
        }

        $row = DB::table('subscriptions.recurring_subscriptions')->where('id', $subscriptionId)->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    public function findByProviderReferenceForUpdate(string $provider, string $providerReference): ?Subscription
    {
        $subscriptionId = $this->findSubscriptionIdForProviderReference($provider, $providerReference);
        if ($subscriptionId === null) {
            return null;
        }

        $row = DB::table('subscriptions.recurring_subscriptions')
            ->where('id', $subscriptionId)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    private function findSubscriptionIdForProviderReference(string $provider, string $providerReference): ?string
    {
        $subscriptionId = DB::table('subscriptions.subscription_provider_references')
            ->where('provider', $provider)
            ->where('provider_subscription_reference', $providerReference)
            ->value('subscription_id');

        return $subscriptionId === null ? null : (string) $subscriptionId;
    }

    public function save(Subscription $subscription): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $values = [
            'workspace_id' => $subscription->workspaceId(),
            'plan_id' => $subscription->planId(),
            'plan_price_id' => $subscription->planPriceId(),
            'provider' => $subscription->provider(),
            'billing_environment' => $this->environment->current(),
            'provider_subscription_reference' => $subscription->providerReference(),
            'status' => $subscription->status(),
            'current_period_start' => $subscription->currentPeriodStart()->format('Y-m-d H:i:sP'),
            'current_period_end' => $subscription->currentPeriodEnd()->format('Y-m-d H:i:sP'),
            'cancel_at_period_end' => $subscription->cancelAtPeriodEnd(),
            'canceled_at' => $subscription->canceledAt()?->format('Y-m-d H:i:sP'),
            'past_due_since' => $subscription->pastDueSince()?->format('Y-m-d H:i:sP'),
            'last_provider_event_at' => $subscription->lastProviderEventAt()->format('Y-m-d H:i:sP'),
            'version' => $subscription->version(),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ];

        $query = DB::table('subscriptions.recurring_subscriptions')->where('id', $subscription->id()->value);
        $existing = $query->first();
        if ($existing !== null) {
            $query->update($values);
            if (
                $existing->provider !== $subscription->provider()
                || $existing->provider_subscription_reference !== $subscription->providerReference()
            ) {
                DB::table('subscriptions.subscription_provider_references')
                    ->where('provider', $existing->provider)
                    ->where('provider_subscription_reference', $existing->provider_subscription_reference)
                    ->update(['retired_at' => $now->format('Y-m-d H:i:sP')]);
            }
        } else {
            DB::table('subscriptions.recurring_subscriptions')->insert([
                'id' => $subscription->id()->value,
                ...$values,
                'created_at' => $now->format('Y-m-d H:i:sP'),
            ]);
        }

        DB::table('subscriptions.subscription_provider_references')->insertOrIgnore([
            'subscription_id' => $subscription->id()->value,
            'provider' => $subscription->provider(),
            'provider_subscription_reference' => $subscription->providerReference(),
            'created_at' => $now->format('Y-m-d H:i:sP'),
            'retired_at' => null,
        ]);

        $referenceOwner = $this->findSubscriptionIdForProviderReference(
            $subscription->provider(),
            $subscription->providerReference(),
        );
        if ($referenceOwner !== $subscription->id()->value) {
            throw new \DomainException('Subscription provider reference conflict.');
        }
    }
}
