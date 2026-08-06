<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Contracts;

final readonly class CreateWorkspaceCommand
{
    public function __construct(
        public string $name,
        public string $requestedByUserId,
        public ?string $correlationId = null,
        public ?string $workspaceId = null,
    ) {}
}
