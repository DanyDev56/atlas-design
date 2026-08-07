<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Application;

use Atlas\Modules\Analytics\Domain\AnalyticsSnapshotPublished;
use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsFactRepository;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsSnapshotRepository;
use Atlas\Composition\Analytics\SourceFactSummaryBuilder;
use Atlas\Modules\Analytics\Infrastructure\PostgresAnalyticsIdempotencyStore;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutgoingMessage;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\Facades\DB;

final class PublishAnalyticsSnapshotHandler
{
    private const GENERATION_ID = 1;

    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresAnalyticsFactRepository $facts,
        private readonly PostgresAnalyticsSnapshotRepository $snapshots,
        private readonly MetricCalculator $calculator,
        private readonly PostgresAnalyticsIdempotencyStore $idempotency,
        private readonly SourceFactSummaryBuilder $sourceFactSummary,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $actorUserId,
        string $workspaceId,
        string $requestId,
        ?string $correlationId = null,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'analytics.snapshots.publish');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $scope = 'analytics.publish_snapshot';
        $fingerprint = hash('sha256', json_encode([
            $workspaceId,
            MetricKeys::PROFILE_KEY,
            MetricKeys::PROFILE_VERSION,
            $now->format('Y-m-d H'),
        ], JSON_THROW_ON_ERROR));
        $cached = $this->idempotency->find($scope, $requestId);

        if ($cached !== null) {
            if ($cached['fingerprint'] !== $fingerprint) {
                throw new \DomainException('Idempotency conflict.');
            }

            return $cached['response_payload'];
        }

        return DB::transaction(function () use (
            $workspaceId, $requestId, $scope, $fingerprint, $now, $correlationId,
        ): array {
            $crmWatermark = $this->facts->getWatermark($workspaceId, 'crm');
            $billingWatermark = $this->facts->getWatermark($workspaceId, 'billing');

            if ($crmWatermark === null || $billingWatermark === null) {
                throw new \DomainException('Insufficient data.');
            }

            $crmLag = $now->getTimestamp() - (new \DateTimeImmutable($crmWatermark['complete_through']))->getTimestamp();
            $billingLag = $now->getTimestamp() - (new \DateTimeImmutable($billingWatermark['complete_through']))->getTimestamp();
            $snapshotLag = max($crmLag, $billingLag);
            $freshness = $snapshotLag <= 3600
                ? MetricKeys::FRESHNESS_CURRENT
                : MetricKeys::FRESHNESS_LAGGING;

            if ($snapshotLag > 86400) {
                throw new \DomainException('Stale data.');
            }

            $metrics = $this->calculator->calculateAll($workspaceId, $now);
            $watermarks = [
                'crm' => $crmWatermark['complete_through'],
                'billing' => $billingWatermark['complete_through'],
            ];
            $sourceFactSummary = $this->sourceFactSummary->build($workspaceId, $now);

            $snapshotId = $this->snapshots->insert(
                workspaceId: $workspaceId,
                profileKey: MetricKeys::PROFILE_KEY,
                profileVersion: MetricKeys::PROFILE_VERSION,
                generationId: self::GENERATION_ID,
                asOf: $now,
                freshnessStatus: $freshness,
                completenessStatus: MetricKeys::COMPLETENESS_COMPLETE,
                metrics: $metrics,
                watermarks: $watermarks,
                sourceFactSummary: $sourceFactSummary,
            );

            $event = new AnalyticsSnapshotPublished(
                analyticsSnapshotId: $snapshotId,
                workspaceId: $workspaceId,
                profileKey: MetricKeys::PROFILE_KEY,
                profileVersion: MetricKeys::PROFILE_VERSION,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event, correlationId: $correlationId));

            $response = [
                'analytics_snapshot_id' => $snapshotId,
                'profile_key' => MetricKeys::PROFILE_KEY,
                'profile_version' => MetricKeys::PROFILE_VERSION,
                'freshness_status' => $freshness,
                'completeness_status' => MetricKeys::COMPLETENESS_COMPLETE,
                'as_of' => $now->format(DATE_ATOM),
            ];

            $this->idempotency->store($scope, $requestId, $fingerprint, $response);

            return $response;
        });
    }
}
