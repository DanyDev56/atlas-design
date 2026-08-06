<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Contracts;

final readonly class ActivateWorkspaceResult
{
    public function __construct(
        public string $workspaceId,
        public string $status,
        public string $accessState,
        public int $governanceVersion,
        public string $eventId,
    ) {}
}
