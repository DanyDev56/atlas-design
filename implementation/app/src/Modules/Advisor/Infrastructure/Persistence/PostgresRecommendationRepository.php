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
            'revision' => 1,
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

    /** @param array<string, string> $terminalDecision */
    public function recordTerminalDecision(
        string $workspaceId,
        string $recommendationId,
        int $expectedRevision,
        string $status,
        array $terminalDecision,
        \DateTimeImmutable $decidedAt,
    ): array {
        $row = DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->where('id', $recommendationId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new \DomainException('Recommendation not found.');
        }

        if ($row->status !== RecommendationPolicy::STATUS_GENERATED) {
            throw new \DomainException('Recommendation no longer active.');
        }

        if (new \DateTimeImmutable($row->valid_until) <= $decidedAt) {
            throw new \DomainException('Recommendation no longer active.');
        }

        if ((int) $row->revision !== $expectedRevision) {
            throw new \DomainException('Revision conflict.');
        }

        $nextRevision = $expectedRevision + 1;
        $updated = DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->where('id', $recommendationId)
            ->where('status', RecommendationPolicy::STATUS_GENERATED)
            ->where('revision', $expectedRevision)
            ->update([
                'status' => $status,
                'revision' => $nextRevision,
                'terminal_decision' => json_encode($terminalDecision, JSON_THROW_ON_ERROR),
                'terminal_at' => $decidedAt->format('Y-m-d H:i:sP'),
            ]);

        if ($updated !== 1) {
            throw new \DomainException('Revision conflict.');
        }

        return [
            'recommendation_id' => $recommendationId,
            'status' => $status,
            'revision' => $nextRevision,
            'terminal_decision' => $terminalDecision,
            'terminal_at' => $decidedAt->format(DATE_ATOM),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function findActiveIds(string $workspaceId, array $ids, \DateTimeImmutable $at): array
    {
        if ($ids === []) {
            return [];
        }

        $active = DB::table('advisor.recommendations')
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->where('status', RecommendationPolicy::STATUS_GENERATED)
            ->where('valid_until', '>', $at->format('Y-m-d H:i:sP'))
            ->pluck('id')
            ->all();

        $activeLookup = array_fill_keys($active, true);

        return array_values(array_filter(
            $ids,
            static fn (string $id): bool => isset($activeLookup[$id]),
        ));
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
            'revision' => (int) $row['revision'],
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
            'terminal_decision' => $row['terminal_decision'] !== null
                ? json_decode($row['terminal_decision'], true, 512, JSON_THROW_ON_ERROR)
                : null,
            'terminal_at' => $row['terminal_at'],
        ];
    }
}
