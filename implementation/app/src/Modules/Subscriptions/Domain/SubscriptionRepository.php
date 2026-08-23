<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

interface SubscriptionRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Subscription;

    public function lockProviderReference(string $provider, string $providerReference): void;

    public function findByProviderReference(string $provider, string $providerReference): ?Subscription;

    public function findByProviderReferenceForUpdate(string $provider, string $providerReference): ?Subscription;

    public function save(Subscription $subscription): void;
}
