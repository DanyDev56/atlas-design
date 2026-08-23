<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

interface WorkspaceEntitlementEnforcer
{
    public function enforce(string $workspaceId, string $capability): ?EntitlementDecision;
}
