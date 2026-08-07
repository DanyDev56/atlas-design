<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Application;

use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresBusinessHealthAssessmentRepository;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresCurrentBusinessHealthRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class BusinessHealthQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresCurrentBusinessHealthRepository $current,
        private readonly PostgresBusinessHealthAssessmentRepository $assessments,
    ) {}

    /** @return array<string, mixed> */
    public function getCurrent(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'business-health.assessments.read');

        $assessment = $this->current->findCurrent($workspaceId, HealthPolicy::VERSION);

        if ($assessment === null) {
            throw new \DomainException('Assessment not found.');
        }

        return $assessment;
    }

    /** @return array<string, mixed> */
    public function getAssessment(string $actorUserId, string $workspaceId, string $assessmentId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'business-health.assessments.read');

        $assessment = $this->assessments->findById($workspaceId, $assessmentId);

        if ($assessment === null) {
            throw new \DomainException('Assessment not found.');
        }

        return $assessment;
    }
}
