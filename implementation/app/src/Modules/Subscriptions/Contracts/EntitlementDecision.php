<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final readonly class EntitlementDecision
{
    public function __construct(
        public string $workspaceId,
        public string $capability,
        public bool $granted,
        public string $accessLevel,
        public string $sourceType,
        public ?string $validUntil,
        public bool $enforcementEnabled,
    ) {}
}
