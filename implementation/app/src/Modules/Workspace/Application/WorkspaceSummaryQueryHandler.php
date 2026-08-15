<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class WorkspaceSummaryQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly WorkspaceRepository $workspaces,
    ) {}

    /** @return array{workspace_id: string, display_name: string, access_state: string, version: int} */
    public function handle(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.settings.read');

        $workspace = $this->workspaces->findById(new WorkspaceId($workspaceId));

        if ($workspace === null) {
            throw new \DomainException('Workspace not found.');
        }

        return [
            'workspace_id' => $workspace->id()->value,
            'display_name' => $workspace->name(),
            'access_state' => $workspace->accessState(),
            'version' => $workspace->version(),
        ];
    }
}
