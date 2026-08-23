<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

interface EntitlementRepository
{
    public function findByWorkspaceId(string $workspaceId): ?Entitlement;

    public function save(Entitlement $entitlement): void;
}
