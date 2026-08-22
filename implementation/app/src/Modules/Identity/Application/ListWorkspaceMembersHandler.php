<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class ListWorkspaceMembersHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresMembershipRepository $memberships,
    ) {}

    /** @return array{members: list<array<string, mixed>>} */
    public function handle(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.members.read');

        return [
            'members' => $this->memberships->listForWorkspace($workspaceId),
        ];
    }
}
