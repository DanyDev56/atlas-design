<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Domain\TrialRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class SubscriptionOverviewQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PlanCatalogRepository $catalog,
        private readonly TrialRepository $trials,
        private readonly EntitlementRepository $entitlements,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'subscriptions.read');

        $plan = $this->catalog->findVersion(
            (string) config('subscriptions.default_plan.code', 'atlas_solo'),
            (int) config('subscriptions.default_plan.version', 1),
        );
        if ($plan === null) {
            throw new \DomainException('Default subscription plan unavailable.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $trial = $this->trials->findByWorkspaceId($workspaceId);
        $entitlement = $this->entitlements->findByWorkspaceId($workspaceId);

        return [
            'workspace_id' => $workspaceId,
            'catalog' => [
                'status' => $plan->status,
                'public' => $plan->public,
                'plan' => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'version' => $plan->version,
                    'display_name' => $plan->displayName,
                    'capabilities' => $plan->capabilities,
                    'limits' => $plan->limits,
                    'prices' => array_map(static fn ($price): array => [
                        'id' => $price->id,
                        'billing_interval' => $price->interval->value,
                        'currency' => $price->currency,
                        'amount_minor' => $price->amountMinor,
                        'status' => $price->status,
                    ], $plan->prices),
                ],
            ],
            'trial' => $trial === null ? null : [
                'id' => $trial->id()->value,
                'status' => $trial->effectiveStatusAt($now),
                'started_at' => $trial->startedAt()->format(DATE_ATOM),
                'ends_at' => $trial->endsAt()->format(DATE_ATOM),
                'remaining_days' => $trial->remainingDaysAt($now),
            ],
            'subscription' => null,
            'access' => $entitlement === null ? [
                'level' => 'Provisioning',
                'source' => 'None',
                'valid_until' => null,
                'capabilities' => [],
                'limits' => [],
            ] : [
                'level' => $entitlement->accessLevelAt($now)->value,
                'source' => $entitlement->sourceType,
                'valid_until' => $entitlement->validUntil?->format(DATE_ATOM),
                'capabilities' => $entitlement->accessLevelAt($now)->value === 'Full'
                    ? $entitlement->fullCapabilities
                    : $entitlement->restrictedCapabilities,
                'limits' => $entitlement->limits,
            ],
            'commercialization' => [
                'enforcement_enabled' => (bool) config('subscriptions.enforcement_enabled', false),
                'checkout_enabled' => (bool) config('subscriptions.checkout_enabled', false),
                'gateway' => (string) config('subscriptions.gateway', 'fake'),
            ],
        ];
    }
}
