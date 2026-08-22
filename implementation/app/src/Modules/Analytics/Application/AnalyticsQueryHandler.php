<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Application;

use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsSnapshotRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class AnalyticsQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresAnalyticsSnapshotRepository $snapshots,
        private readonly MetricCalculator $calculator,
    ) {}

    /** @return array<string, mixed> */
    public function getLatestSnapshot(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'analytics.metrics.read');

        $snapshot = $this->snapshots->findLatest(
            $workspaceId,
            MetricKeys::PROFILE_KEY,
            MetricKeys::PROFILE_VERSION,
        );

        if ($snapshot === null) {
            throw new \DomainException('Snapshot not found.');
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    public function getOverview(string $actorUserId, string $workspaceId): array
    {
        $snapshot = $this->getLatestSnapshot($actorUserId, $workspaceId);
        $metrics = [];

        foreach (MetricKeys::overviewKeys() as $metricKey) {
            $observation = $snapshot['metrics'][$metricKey] ?? [
                'window_kind' => 'PointInTime',
                'value_status' => MetricKeys::VALUE_NO_DATA,
            ];
            $metrics[$metricKey] = is_array($observation) ? $observation : [
                'window_kind' => 'PointInTime',
                'value_status' => MetricKeys::VALUE_NO_DATA,
            ];
        }

        return [
            'overview_profile_key' => $snapshot['profile_key'],
            'overview_profile_version' => $snapshot['profile_version'],
            'analytics_snapshot_id' => $snapshot['analytics_snapshot_id'],
            'as_of' => $snapshot['as_of'],
            'freshness_status' => $snapshot['freshness_status'],
            'completeness_status' => $snapshot['completeness_status'],
            'metrics' => $metrics,
        ];
    }

    /** @return array<string, mixed> */
    public function getMetric(string $actorUserId, string $workspaceId, string $metricKey): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'analytics.metrics.read');

        if (! in_array($metricKey, MetricKeys::all(), true)) {
            throw new \DomainException('Metric not found.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return [
            'metric_key' => $metricKey,
            'definition_version' => '1.0.0',
            'observation' => $this->calculator->calculateMetric($workspaceId, $metricKey, $now),
            'calculated_at' => $now->format(DATE_ATOM),
        ];
    }
}
