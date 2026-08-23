<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresSubscriptionRepository implements SubscriptionRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Subscription
    {
        $row = DB::table('subscriptions.recurring_subscriptions')->where('workspace_id', $workspaceId)->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    public function lockProviderReference(string $provider, string $providerReference): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$provider, $providerReference]);
    }

    public function findByProviderReference(string $provider, string $providerReference): ?Subscription
    {
        $row = $this->findByProviderReferenceQuery($provider, $providerReference)->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    public function findByProviderReferenceForUpdate(string $provider, string $providerReference): ?Subscription
    {
        $row = $this->findByProviderReferenceQuery($provider, $providerReference)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : Subscription::reconstitute((array) $row);
    }

    private function findByProviderReferenceQuery(string $provider, string $providerReference): Builder
    {
        return DB::table('subscriptions.recurring_subscriptions')
            ->where('provider', $provider)
            ->where('provider_subscription_reference', $providerReference);
    }

    public function save(Subscription $subscription): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $values = [
            'workspace_id' => $subscription->workspaceId(),
            'plan_id' => $subscription->planId(),
            'plan_price_id' => $subscription->planPriceId(),
            'provider' => $subscription->provider(),
            'provider_subscription_reference' => $subscription->providerReference(),
            'status' => $subscription->status(),
            'current_period_start' => $subscription->currentPeriodStart()->format('Y-m-d H:i:sP'),
            'current_period_end' => $subscription->currentPeriodEnd()->format('Y-m-d H:i:sP'),
            'cancel_at_period_end' => $subscription->cancelAtPeriodEnd(),
            'canceled_at' => $subscription->canceledAt()?->format('Y-m-d H:i:sP'),
            'last_provider_event_at' => $subscription->lastProviderEventAt()->format('Y-m-d H:i:sP'),
            'version' => $subscription->version(),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ];

        $query = DB::table('subscriptions.recurring_subscriptions')->where('id', $subscription->id()->value);
        if ($query->exists()) {
            $query->update($values);

            return;
        }

        DB::table('subscriptions.recurring_subscriptions')->insert([
            'id' => $subscription->id()->value,
            ...$values,
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }
}
