<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Contracts;

final readonly class ActivateWorkspaceCommand
{
    public function __construct(
        public string $workspaceId,
        public string $ownerReadinessProofId,
        public bool $hasActiveOwner,
        public int $expectedRevision,
        public string $requestId,
        public ?string $correlationId = null,
    ) {}
}
