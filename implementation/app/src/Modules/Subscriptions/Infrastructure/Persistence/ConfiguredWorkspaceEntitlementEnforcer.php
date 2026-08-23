<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Contracts\EntitlementDecision;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionAccessRestrictedException;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionPolicyUnavailableException;
use Atlas\Modules\Subscriptions\Contracts\WorkspaceEntitlementEnforcer;
use Atlas\Modules\Subscriptions\Contracts\WorkspaceEntitlementReader;

final readonly class ConfiguredWorkspaceEntitlementEnforcer implements WorkspaceEntitlementEnforcer
{
    public function __construct(private WorkspaceEntitlementReader $entitlements) {}

    public function enforce(string $workspaceId, string $capability): ?EntitlementDecision
    {
        if (! config('subscriptions.enforcement_enabled', false)) {
            return null;
        }
        if (config('subscriptions.past_due_grace_days') === null) {
            throw new SubscriptionPolicyUnavailableException;
        }

        $decision = $this->entitlements->decide($workspaceId, $capability);
        if (! $decision->granted) {
            throw new SubscriptionAccessRestrictedException($decision);
        }

        return $decision;
    }
}
