<?php

declare(strict_types=1);

namespace Atlas\Modules\Notifications\Application;

use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationPreferenceRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class ChangeNotificationPreferencesHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresNotificationPreferenceRepository $preferences,
    ) {}

    /** @return array<string, mixed> */
    public function getPreferences(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.preferences.read');

        $prefs = $this->preferences->findOrDefault($workspaceId, $actorUserId);

        return [
            'in_app_mode' => $prefs['in_app_mode'],
            'email_mode' => $prefs['email_mode'],
            'revision' => $prefs['revision'],
        ];
    }

    /** @return array<string, mixed> */
    public function changePreferences(
        string $actorUserId,
        string $workspaceId,
        string $inAppMode,
        string $emailMode,
        int $expectedRevision,
        bool $emailConsentConfirmed,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'notifications.preferences.change');

        if ($emailMode === NotificationPolicy::EMAIL_IMPORTANT_ONLY && ! $emailConsentConfirmed) {
            throw new \DomainException('Consent required.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $revision = $this->preferences->upsert(
            workspaceId: $workspaceId,
            userId: $actorUserId,
            inAppMode: $inAppMode,
            emailMode: $emailMode,
            revision: $expectedRevision,
            updatedAt: $now,
        );

        return [
            'in_app_mode' => $inAppMode,
            'email_mode' => $emailMode,
            'revision' => $revision,
        ];
    }
}
