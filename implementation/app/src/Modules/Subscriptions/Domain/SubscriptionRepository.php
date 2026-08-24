<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

interface SubscriptionRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Subscription;

    public function findById(string $subscriptionId): ?Subscription;

    public function lockWorkspace(string $workspaceId): void;

    public function findByWorkspaceIdForUpdate(string $workspaceId): ?Subscription;

    public function findByIdForUpdate(string $subscriptionId): ?Subscription;

    public function lockProviderReference(string $provider, string $providerReference): void;

    public function findByProviderReference(string $provider, string $providerReference): ?Subscription;

    public function findByProviderReferenceForUpdate(string $provider, string $providerReference): ?Subscription;

    public function save(Subscription $subscription): void;
}
