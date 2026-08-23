<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingWebhookInbox;
use Atlas\Modules\Subscriptions\Contracts\RecurringBillingWebhookVerifier;
use Atlas\Modules\Subscriptions\Domain\Entitlement;
use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;
use Atlas\Modules\Subscriptions\Domain\Plan;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Atlas\Modules\Subscriptions\Domain\SubscriptionId;
use Atlas\Modules\Subscriptions\Domain\SubscriptionRepository;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;
use Illuminate\Support\Facades\DB;

final class ProcessRecurringBillingWebhookHandler
{
    private const array RESTRICTED_CAPABILITIES = [
        'workspace.read',
        'data.export',
        'subscription.manage',
    ];

    public function __construct(
        private readonly RecurringBillingWebhookVerifier $verifier,
        private readonly RecurringBillingWebhookInbox $inbox,
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanCatalogRepository $catalog,
        private readonly EntitlementRepository $entitlements,
    ) {}

    /** @return array{provider_event_id: string, status: string, duplicate: bool} */
    public function handle(string $provider, string $payload, string $signature): array
    {
        $event = $this->verifier->verify($provider, $payload, $signature);
        $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        $record = $this->inbox->record($event, hash('sha256', $payload), $decoded);

        if (in_array($record['status'], ['Processed', 'Ignored'], true)) {
            return $this->result($event, $record['status'], true);
        }
        if (! $this->inbox->claim($provider, $event->providerEventId)) {
            $current = $this->inbox->find($provider, $event->providerEventId);

            return $this->result($event, (string) ($current['status'] ?? 'Processing'), true);
        }

        return $this->process($event, duplicate: ! $record['inserted']);
    }

    /** @return array{provider_event_id: string, status: string, duplicate: bool} */
    public function replay(string $provider, string $providerEventId): array
    {
        $record = $this->inbox->find($provider, $providerEventId);
        if ($record === null) {
            throw new \DomainException('Webhook event not found.');
        }
        if (in_array((string) $record['status'], ['Processed', 'Ignored'], true)) {
            $event = $this->verifier->decodeTrusted(
                $provider,
                is_string($record['payload']) ? $record['payload'] : json_encode($record['payload'], JSON_THROW_ON_ERROR),
            );

            return $this->result($event, (string) $record['status'], true);
        }

        if (! $this->inbox->claim($provider, $providerEventId)) {
            $current = $this->inbox->find($provider, $providerEventId);
            $event = $this->verifier->decodeTrusted(
                $provider,
                is_string($record['payload']) ? $record['payload'] : json_encode($record['payload'], JSON_THROW_ON_ERROR),
            );

            return $this->result($event, (string) ($current['status'] ?? 'Processing'), true);
        }

        $payload = is_string($record['payload'])
            ? $record['payload']
            : json_encode($record['payload'], JSON_THROW_ON_ERROR);
        $event = $this->verifier->decodeTrusted($provider, $payload);

        return $this->process($event, duplicate: true);
    }

    /** @return array{provider_event_id: string, status: string, duplicate: bool} */
    private function process(VerifiedRecurringBillingEvent $event, bool $duplicate): array
    {
        try {
            $status = DB::transaction(function () use ($event): string {
                $plan = $this->defaultPlanFor($event);
                $this->subscriptions->lockProviderReference(
                    $event->provider,
                    $event->providerSubscriptionReference,
                );
                $subscription = $this->subscriptions->findByProviderReferenceForUpdate(
                    $event->provider,
                    $event->providerSubscriptionReference,
                );

                if ($subscription === null) {
                    if ($event->type !== RecurringBillingEventType::Activated) {
                        return 'Deferred';
                    }
                    if ($this->subscriptions->findByWorkspaceId($event->workspaceId) !== null) {
                        throw new \DomainException('Workspace already has a subscription.');
                    }
                    $subscription = Subscription::activate(SubscriptionId::generate(), $plan->id, $event);
                } elseif (! $subscription->apply($event)) {
                    return 'Ignored';
                }

                $this->subscriptions->save($subscription);
                $previous = $this->entitlements->findByWorkspaceId($event->workspaceId);
                $this->entitlements->save(new Entitlement(
                    workspaceId: $event->workspaceId,
                    sourceType: 'Subscription',
                    sourceId: $subscription->id()->value,
                    fullCapabilities: $plan->capabilities,
                    restrictedCapabilities: self::RESTRICTED_CAPABILITIES,
                    limits: $plan->limits,
                    validUntil: $subscription->currentPeriodEnd(),
                    computedAt: $event->occurredAt,
                    version: ($previous?->version ?? 0) + 1,
                ));

                return 'Processed';
            });

            $this->inbox->mark($event->provider, $event->providerEventId, $status);

            return $this->result($event, $status, $duplicate);
        } catch (\Throwable $exception) {
            $this->inbox->mark($event->provider, $event->providerEventId, 'Failed', $exception::class);
            throw $exception;
        }
    }

    private function defaultPlanFor(VerifiedRecurringBillingEvent $event): Plan
    {
        $plan = $this->catalog->findVersion(
            (string) config('subscriptions.default_plan.code', 'atlas_solo'),
            (int) config('subscriptions.default_plan.version', 1),
        );
        $priceAvailable = false;
        foreach ($plan?->prices ?? [] as $price) {
            if ($price->id === $event->planPriceId) {
                $priceAvailable = true;
                break;
            }
        }
        if ($plan === null || ! $priceAvailable) {
            throw new \DomainException('Webhook plan price unavailable.');
        }

        return $plan;
    }

    /** @return array{provider_event_id: string, status: string, duplicate: bool} */
    private function result(VerifiedRecurringBillingEvent $event, string $status, bool $duplicate): array
    {
        return [
            'provider_event_id' => $event->providerEventId,
            'status' => $status,
            'duplicate' => $duplicate,
        ];
    }
}
