<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Contracts\OwnerReadinessProof;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Platform\Support\UuidGenerator;

final class GetWorkspaceOwnerReadinessHandler
{
    public function __construct(
        private readonly PostgresMembershipRepository $memberships,
    ) {}

    public function handle(string $workspaceId): OwnerReadinessProof
    {
        $hasActiveOwner = $this->memberships->hasActiveOwner($workspaceId);

        return new OwnerReadinessProof(
            proofId: UuidGenerator::generate(),
            workspaceId: $workspaceId,
            hasActiveOwner: $hasActiveOwner,
            issuedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
