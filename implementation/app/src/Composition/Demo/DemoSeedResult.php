<?php

declare(strict_types=1);

namespace Atlas\Composition\Demo;

final class DemoSeedResult
{
    /** @param array<string, int> $resourceCounts */
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $userId,
        public readonly string $workspaceId,
        public readonly bool $userCreated,
        public readonly bool $sampleDataSeeded,
        public readonly array $resourceCounts,
    ) {}
}
