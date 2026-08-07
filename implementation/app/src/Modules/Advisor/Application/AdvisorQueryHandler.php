<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Application;

use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresAdvisorOverviewRepository;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresRecommendationRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class AdvisorQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresAdvisorOverviewRepository $overviews,
        private readonly PostgresRecommendationRepository $recommendations,
    ) {}

    /** @return array<string, mixed> */
    public function getOverview(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'advisor.recommendations.read');

        $overview = $this->overviews->findCurrent($workspaceId);

        if ($overview === null) {
            throw new \DomainException('Overview not found.');
        }

        $recommendations = $this->recommendations->findByIds(
            $workspaceId,
            $overview['recommendation_ids'],
        );

        $primary = null;
        $alternatives = [];

        foreach ($recommendations as $recommendation) {
            if ($recommendation['recommendation_id'] === $overview['primary_recommendation_id']) {
                $primary = $recommendation;
            } else {
                $alternatives[] = $recommendation;
            }
        }

        return [
            'advisor_overview_version' => $overview['advisor_overview_version'],
            'source_eligibility' => $overview['source_eligibility'],
            'primary_recommendation' => $primary,
            'alternative_recommendations' => $alternatives,
            'updated_at' => $overview['updated_at'],
        ];
    }
}
