<?php

declare(strict_types=1);

namespace Atlas\Composition\Onboarding;

use Atlas\Composition\Onboarding\Infrastructure\PostgresBootstrapWorkflowRepository;
use Atlas\Modules\Identity\Application\BootstrapIdentityForWorkspaceHandler;
use Atlas\Modules\Identity\Application\GetWorkspaceOwnerReadinessHandler;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Modules\Workspace\Application\ActivateWorkspaceHandler;
use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Contracts\ActivateWorkspaceCommand;
use Atlas\Modules\Workspace\Contracts\CreateWorkspaceCommand;
use Atlas\Modules\Workspace\Domain\Workspace;
use Atlas\Modules\Workspace\Domain\WorkspaceId;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Platform\Support\UuidGenerator;

final class BootstrapFirstWorkspaceHandler
{
    public function __construct(
        private readonly PostgresBootstrapWorkflowRepository $workflows,
        private readonly PostgresIdempotencyStore $idempotency,
        private readonly CreateWorkspaceHandler $createWorkspace,
        private readonly BootstrapIdentityForWorkspaceHandler $bootstrapIdentity,
        private readonly GetWorkspaceOwnerReadinessHandler $ownerReadiness,
        private readonly ActivateWorkspaceHandler $activateWorkspace,
        private readonly WorkspaceRepository $workspaceRepository,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $userId, string $workspaceName, string $idempotencyKey, ?string $correlationId = null): array
    {
        $scope = 'onboarding.bootstrap_first_workspace';
        $fingerprint = hash('sha256', json_encode([$userId, $workspaceName], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $idempotencyKey);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            $workspaceId = (string) ($cached['response_payload']['workspace_id'] ?? '');
            if ($workspaceId !== '') {
                $this->bootstrapIdentity->handle($userId, $workspaceId);
            }

            return $cached['response_payload'];
        }

        $workflow = $this->workflows->findByIdempotencyKey($idempotencyKey);

        if ($workflow === null) {
            $workflowId = $this->workflows->create($userId, $idempotencyKey);
            $workflow = ['id' => $workflowId, 'workspace_id' => null, 'status' => 'started'];
        }

        $workspaceId = $workflow['workspace_id'];

        if ($workspaceId === null) {
            $workspaceId = UuidGenerator::generate();
            $created = $this->createWorkspace->handle(new CreateWorkspaceCommand(
                name: $workspaceName,
                requestedByUserId: $userId,
                correlationId: $correlationId,
                workspaceId: $workspaceId,
            ));
            $workspaceId = $created->workspaceId;
            $this->workflows->attachWorkspace($workflow['id'], $workspaceId, 'workspace_created');
        }

        $this->bootstrapIdentity->handle($userId, $workspaceId);
        $this->workflows->updateStatus($workflow['id'], 'identity_bootstrapped');

        $proof = $this->ownerReadiness->handle($workspaceId);

        $workspace = $this->workspaceRepository->findById(new WorkspaceId($workspaceId));

        if ($workspace === null) {
            throw new \DomainException('Workspace not found after creation.');
        }

        if ($workspace->status() === Workspace::STATUS_PROVISIONING) {
            $this->activateWorkspace->handle(new ActivateWorkspaceCommand(
                workspaceId: $workspaceId,
                ownerReadinessProofId: $proof->proofId,
                hasActiveOwner: $proof->hasActiveOwner,
                expectedRevision: $workspace->version(),
                requestId: $idempotencyKey.':activate',
                correlationId: $correlationId,
            ));
        }

        $workspace = $this->workspaceRepository->findById(new WorkspaceId($workspaceId));
        $this->workflows->updateStatus($workflow['id'], 'completed');

        $response = [
            'workspace_id' => $workspaceId,
            'status' => $workspace?->status(),
            'access_state' => $workspace?->accessState(),
        ];

        $this->idempotency->store($scope, $idempotencyKey, $fingerprint, $response);

        return $response;
    }
}
