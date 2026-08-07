<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Infrastructure\Persistence;

use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresRecommendationRepository
{
    public function expireGeneratedForWorkspace(string $workspaceId): void
    {
        DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->where('status', RecommendationPolicy::STATUS_GENERATED)
            ->update(['status' => 'Expired']);
    }

    /** @param array<string, mixed> $candidate */
    public function insert(
        string $workspaceId,
        string $businessHealthAssessmentId,
        array $candidate,
        \DateTimeImmutable $generatedAt,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('advisor.recommendations')->insert([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'business_health_assessment_id' => $businessHealthAssessmentId,
            'recommendation_key' => $candidate['recommendation_key'],
            'rule_key' => $candidate['rule_key'],
            'status' => RecommendationPolicy::STATUS_GENERATED,
            'priority' => $candidate['priority'],
            'rank_score' => $candidate['rank_score'],
            'action' => json_encode([
                'module' => $candidate['action_module'],
                'route_key' => $candidate['route_key'],
            ], JSON_THROW_ON_ERROR),
            'expected_impact' => json_encode(['level' => $candidate['impact']], JSON_THROW_ON_ERROR),
            'urgency' => $candidate['urgency'],
            'confidence' => $candidate['confidence'],
            'effort' => $candidate['effort'],
            'valid_until' => $candidate['valid_until'],
            'generated_at' => $generatedAt->format('Y-m-d H:i:sP'),
        ]);

        return $id;
    }

    public function findById(string $workspaceId, string $recommendationId): ?array
    {
        $row = DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->where('id', $recommendationId)
            ->first();

        return $row !== null ? $this->mapRow((array) $row) : null;
    }

    /** @param list<string> $ids */
    /** @return list<array<string, mixed>> */
    public function findByIds(string $workspaceId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->orderByDesc('rank_score')
            ->get()
            ->map(fn ($row) => $this->mapRow((array) $row))
            ->all();
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): array
    {
        $action = json_decode($row['action'], true, 512, JSON_THROW_ON_ERROR);
        $impact = json_decode($row['expected_impact'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'recommendation_id' => $row['id'],
            'recommendation_key' => $row['recommendation_key'],
            'rule_key' => $row['rule_key'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'rank_score' => (int) $row['rank_score'],
            'action_module' => $action['module'],
            'route_key' => $action['route_key'],
            'impact' => $impact['level'],
            'urgency' => $row['urgency'],
            'confidence' => $row['confidence'],
            'effort' => $row['effort'],
            'valid_until' => $row['valid_until'],
            'generated_at' => $row['generated_at'],
        ];
    }
}
