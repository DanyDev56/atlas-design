<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Contracts;

final readonly class OwnerReadinessProof
{
    public function __construct(
        public string $proofId,
        public string $workspaceId,
        public bool $hasActiveOwner,
        public \DateTimeImmutable $issuedAt,
    ) {}

    public function isValidFor(string $workspaceId, \DateTimeImmutable $now, int $maxAgeSeconds = 300): bool
    {
        if ($this->workspaceId !== $workspaceId || ! $this->hasActiveOwner) {
            return false;
        }

        return ($now->getTimestamp() - $this->issuedAt->getTimestamp()) <= $maxAgeSeconds;
    }
}
