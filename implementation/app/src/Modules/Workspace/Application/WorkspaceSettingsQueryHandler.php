<?php

declare(strict_types=1);

namespace Atlas\Modules\Workspace\Application;

use Atlas\Modules\Workspace\Domain\Workspace;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class WorkspaceSettingsQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly WorkspaceRepository $workspaces,
    ) {}

    /** @return array<string, mixed> */
    public function getProfile(string $actorUserId, string $workspaceId): array
    {
        $workspace = $this->requireWorkspace($actorUserId, $workspaceId, 'workspace.profile.read');

        return [
            'workspace_id' => $workspace->id()->value,
            'display_name' => $workspace->name(),
            'trading_name' => $workspace->tradingName(),
            'activity_description' => $workspace->activityDescription(),
            'profile_version' => $workspace->profileVersion(),
        ];
    }

    /** @return array<string, mixed> */
    public function getBillingIdentity(string $actorUserId, string $workspaceId): array
    {
        $workspace = $this->requireWorkspace($actorUserId, $workspaceId, 'workspace.billing-identity.read');

        return [
            'workspace_id' => $workspace->id()->value,
            'legal_name' => $workspace->legalName(),
            'administrative_email' => $workspace->administrativeEmail(),
            'billing_identity_version' => $workspace->billingIdentityVersion(),
        ];
    }

    /** @return array<string, mixed> */
    public function getPreferences(string $actorUserId, string $workspaceId): array
    {
        $workspace = $this->requireWorkspace($actorUserId, $workspaceId, 'workspace.preferences.read');

        return [
            'workspace_id' => $workspace->id()->value,
            'locale' => $workspace->locale(),
            'timezone' => $workspace->timezone(),
            'default_currency' => $workspace->defaultCurrency(),
            'establishment_country' => $workspace->establishmentCountry(),
            'preferences_version' => $workspace->preferencesVersion(),
        ];
    }

    private function requireWorkspace(string $actorUserId, string $workspaceId, string $permission): Workspace
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, $permission);

        $workspace = $this->workspaces->findById(new WorkspaceId($workspaceId));
        if ($workspace === null) {
            throw new \DomainException('Workspace not found.');
        }

        return $workspace;
    }
}
