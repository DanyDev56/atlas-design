<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;

final class GetSessionContextHandler
{
    public function __construct(
        private readonly PostgresMembershipRepository $memberships,
        private readonly PostgresSessionRepository $sessions,
    ) {}

    /** @return array{user_id: string, workspace_id: string|null, elevation_expires_at: string|null} */
    public function handle(string $userId, string $sessionId): array
    {
        $expiresAt = $this->sessions->activeElevationExpiresAt($sessionId);

        return [
            'user_id' => $userId,
            'workspace_id' => $this->memberships->findPrimaryWorkspaceId(new UserId($userId)),
            'elevation_expires_at' => $expiresAt?->format(DATE_ATOM),
        ];
    }
}
