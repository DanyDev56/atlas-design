<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Domain;

use Atlas\Platform\Messaging\DomainEvent;
use Atlas\Platform\Messaging\EventId;

final readonly class BusinessHealthAssessed implements DomainEvent
{
    public function __construct(
        public string $businessHealthAssessmentId,
        public string $analyticsSnapshotId,
        public string $workspaceId,
        public string $healthPolicyVersion,
        public string $assessmentStatus,
        public string $assessmentReliability,
        public ?int $overallScore,
        public ?string $healthBand,
        public string $healthTrend,
        public ?string $primaryAttentionFactorKey,
        private EventId $eventId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        return 'business_health.assessed';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'business_health_assessment_id' => $this->businessHealthAssessmentId,
            'analytics_snapshot_id' => $this->analyticsSnapshotId,
            'workspace_id' => $this->workspaceId,
            'health_policy_version' => $this->healthPolicyVersion,
            'assessment_status' => $this->assessmentStatus,
            'assessment_reliability' => $this->assessmentReliability,
            'overall_score' => $this->overallScore,
            'health_band' => $this->healthBand,
            'health_trend' => $this->healthTrend,
            'primary_attention_factor_key' => $this->primaryAttentionFactorKey,
        ];
    }
}
