<?php

declare(strict_types=1);

namespace Atlas\Composition\Advisor;

use Atlas\Modules\Advisor\Application\EvaluateRecommendationsHandler;
use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxConsumer;

final class OutboxAdvisorEvaluateConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly EvaluateRecommendationsHandler $evaluate,
    ) {}

    public function name(): string
    {
        return 'composition.advisor_evaluate';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'business_health.assessed') {
            return;
        }

        $payload = $message->payload;
        $assessmentId = $payload['business_health_assessment_id'];
        $workspaceId = $payload['workspace_id'];
        $requestId = hash('sha256', $assessmentId.'|'.RecommendationPolicy::VERSION);

        $this->evaluate->handle(
            workspaceId: $workspaceId,
            businessHealthAssessmentId: $assessmentId,
            requestId: $requestId,
        );
    }
}
