<?php

declare(strict_types=1);

namespace Atlas\Composition\BusinessHealth;

use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Modules\BusinessHealth\Application\EvaluateBusinessHealthHandler;
use Atlas\Modules\BusinessHealth\Domain\HealthPolicy;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxConsumer;

final class OutboxBusinessHealthEvaluateConsumer implements OutboxConsumer
{
    public function __construct(
        private readonly EvaluateBusinessHealthHandler $evaluate,
    ) {}

    public function name(): string
    {
        return 'composition.business_health_evaluate';
    }

    public function handle(OutgoingMessage $message): void
    {
        if ($message->eventType !== 'analytics.snapshot_published') {
            return;
        }

        $payload = $message->payload;

        if (($payload['snapshot_profile_key'] ?? '') !== MetricKeys::PROFILE_KEY
            || ($payload['snapshot_profile_version'] ?? '') !== MetricKeys::PROFILE_VERSION) {
            return;
        }

        $snapshotId = $payload['analytics_snapshot_id'];
        $workspaceId = $payload['workspace_id'];
        $requestId = hash('sha256', $snapshotId.'|'.HealthPolicy::VERSION);

        $this->evaluate->handle(
            workspaceId: $workspaceId,
            analyticsSnapshotId: $snapshotId,
            healthPolicyVersion: HealthPolicy::VERSION,
            requestId: $requestId,
            correlationId: $message->correlationId,
        );
    }
}
