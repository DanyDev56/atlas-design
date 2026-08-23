<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

interface WorkspaceEntitlementReader
{
    public function decide(string $workspaceId, string $capability): EntitlementDecision;
}
