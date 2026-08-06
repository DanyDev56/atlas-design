<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Contracts;

final readonly class CreateWorkspaceResult
{
    public function __construct(
        public string $workspaceId,
        public string $status,
        public string $accessState,
        public string $eventId,
    ) {}
}
