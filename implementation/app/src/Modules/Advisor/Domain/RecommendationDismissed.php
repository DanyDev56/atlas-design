<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class RecommendationDismissed implements DomainEvent
{
    public function __construct(
        public string $workspaceId,
        public string $recommendationId,
        public int $revision,
        public string $dismissalReason,
        public string $actorUserId,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'advisor.recommendation_dismissed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'recommendation_id' => $this->recommendationId,
            'previous_status' => RecommendationPolicy::STATUS_GENERATED,
            'status' => RecommendationPolicy::STATUS_DISMISSED,
            'revision' => $this->revision,
            'dismissed_at' => $this->occurredAt->format(DATE_ATOM),
            'dismissal_reason' => $this->dismissalReason,
            'actor_user_id' => $this->actorUserId,
        ];
    }
}
