<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresInvitationRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class ListWorkspaceInvitationsHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresInvitationRepository $invitations,
    ) {}

    /** @return array{invitations: list<array<string, mixed>>} */
    public function handle(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'workspace.members.invite');

        return [
            'invitations' => $this->invitations->listForWorkspace($workspaceId),
        ];
    }
}
