<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class AdvisorOverviewChanged implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public int $advisorOverviewVersion,
        public string $sourceEligibility,
        public ?string $primaryRecommendationId,
        public string $recommendationPolicyVersion,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'advisor.overview_changed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'advisor_overview_version' => $this->advisorOverviewVersion,
            'source_eligibility' => $this->sourceEligibility,
            'primary_recommendation_id' => $this->primaryRecommendationId,
            'recommendation_policy_version' => $this->recommendationPolicyVersion,
        ];
    }
}
