<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;

final class GetSessionContextHandler
{
    public function __construct(
        private readonly PostgresMembershipRepository $memberships,
    ) {}

    /** @return array{user_id: string, workspace_id: string|null} */
    public function handle(string $userId): array
    {
        return [
            'user_id' => $userId,
            'workspace_id' => $this->memberships->findPrimaryWorkspaceId(new UserId($userId)),
        ];
    }
}
