<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Atlas\Modules\Operations\Domain\BetaCohortCatalog;
use Illuminate\Support\Facades\DB;

final class PostgresBetaCohortSource implements BetaCohortSource
{
    public function participants(\DateTimeImmutable $now, int $blockedAfterDays): array
    {
        return DB::table('operations.beta_participants')
            ->orderBy('beta_code')
            ->get()
            ->map(fn (object $row): array => $this->participant($row, $now, $blockedAfterDays))
            ->all();
    }

    public function diagnostic(string $betaCode): ?array
    {
        $participant = DB::table('operations.beta_participants')
            ->where('beta_code', strtoupper($betaCode))
            ->first();

        if ($participant === null) {
            return null;
        }

        $snapshot = DB::table('analytics.snapshots')
            ->where('workspace_id', $participant->workspace_id)
            ->orderByDesc('published_at')
            ->first();
        $assessment = DB::table('business_health.assessments')
            ->where('workspace_id', $participant->workspace_id)
            ->orderByDesc('assessed_at')
            ->first();

        return [
            'beta_code' => (string) $participant->beta_code,
            'analytics' => $snapshot === null ? [
                'status' => 'NoData',
                'published_at' => null,
                'freshness_status' => null,
                'completeness_status' => null,
            ] : [
                'status' => 'Available',
                'published_at' => (string) $snapshot->published_at,
                'freshness_status' => (string) $snapshot->freshness_status,
                'completeness_status' => (string) $snapshot->completeness_status,
            ],
            'business_health' => $assessment === null ? [
                'status' => 'NoData',
                'assessed_at' => null,
                'assessment_status' => null,
                'reliability' => null,
                'coverage_percent' => null,
            ] : [
                'status' => 'Available',
                'assessed_at' => (string) $assessment->assessed_at,
                'assessment_status' => (string) $assessment->assessment_status,
                'reliability' => (string) $assessment->assessment_reliability,
                'coverage_percent' => (int) $assessment->global_coverage_percent,
            ],
            'source' => 'analytics.snapshots + business_health.assessments',
        ];
    }

    /** @return array<string, mixed> */
    private function participant(object $row, \DateTimeImmutable $now, int $blockedAfterDays): array
    {
        $dates = $this->stageDates($row);
        $currentStage = 'E0';
        foreach (BetaCohortCatalog::STAGES as $stage) {
            if ($dates[$stage] === null) {
                break;
            }
            $currentStage = $stage;
        }

        $review = DB::table('operations.beta_reviews')
            ->where('participant_id', $row->id)
            ->orderByDesc('reviewed_at')
            ->first();
        $observation = DB::table('operations.pricing_observations')
            ->where('participant_id', $row->id)
            ->first();
        $stageAt = new \DateTimeImmutable((string) $dates[$currentStage]);
        $stalledDays = max(0, (int) $stageAt->diff($now)->format('%a'));
        $blockageCode = $review?->blockage_code !== null ? (string) $review->blockage_code : null;

        return [
            'beta_code' => (string) $row->beta_code,
            'status' => (string) $row->status,
            'pricing_cell' => (string) $row->pricing_cell,
            'packaging_version' => (string) $row->packaging_version,
            'segment' => (string) $row->segment,
            'channel' => (string) $row->channel,
            'current_stage' => $currentStage,
            'current_stage_at' => $dates[$currentStage],
            'stage_dates' => $dates,
            'first_value_path' => $this->firstValue($row)['path'],
            'stalled_days' => $stalledDays,
            'blocked' => $blockageCode !== null || ($currentStage !== 'E6' && $stalledDays >= $blockedAfterDays),
            'latest_review' => $review === null ? null : [
                'milestone' => (string) $review->milestone,
                'blockage_code' => $blockageCode,
                'support_minutes' => (int) $review->support_minutes,
                'next_action' => (string) $review->next_action,
                'reviewed_at' => (string) $review->reviewed_at,
            ],
            'pricing_decision' => $observation === null ? null : [
                'decision' => (string) $observation->decision,
                'primary_reason' => (string) $observation->primary_reason,
                'preference' => (string) $observation->preference,
                'observed_on' => (string) $observation->observed_on,
                'has_evidence' => $observation->evidence_ref !== null,
            ],
        ];
    }

    /** @return array<string, string|null> */
    private function stageDates(object $participant): array
    {
        $dates = [
            'E0' => (string) $participant->invited_at,
            'E1' => $this->verifiedAt((string) $participant->user_id),
            'E2' => $this->workspaceReadyAt((string) $participant->workspace_id),
            'E3' => $this->dataReadyAt((string) $participant->workspace_id),
            'E4' => $this->firstValue($participant)['at'],
            'E5' => null,
            'E6' => $this->decisionAt((string) $participant->id),
        ];
        if ($dates['E4'] !== null) {
            $dates['E5'] = $this->reusedAt((string) $participant->workspace_id, $dates['E4']);
        }

        $previousReached = true;
        foreach (BetaCohortCatalog::STAGES as $stage) {
            if (! $previousReached) {
                $dates[$stage] = null;
            }
            $previousReached = $dates[$stage] !== null;
        }

        return $dates;
    }

    private function verifiedAt(string $userId): ?string
    {
        $verified = DB::table('identity.users')
            ->where('id', $userId)
            ->where('email_verification_status', 'Verified')
            ->exists();
        if (! $verified) {
            return null;
        }

        $value = DB::table('identity.sessions')->where('user_id', $userId)->min('created_at');

        return $value !== null ? (string) $value : null;
    }

    private function workspaceReadyAt(string $workspaceId): ?string
    {
        $value = DB::table('workspace.workspaces')
            ->where('id', $workspaceId)
            ->where('status', 'Active')
            ->value('activated_at');

        return $value !== null ? (string) $value : null;
    }

    private function dataReadyAt(string $workspaceId): ?string
    {
        $candidates = [];
        $crmImport = DB::table('crm.client_history_import_runs')->where('workspace_id', $workspaceId)->where('status', 'Completed')->min('updated_at');
        $billingImport = DB::table('billing.history_import_runs')->where('workspace_id', $workspaceId)->where('status', 'Completed')->min('completed_at');
        if ($crmImport !== null) {
            $candidates[] = (string) $crmImport;
        }
        if ($billingImport !== null) {
            $candidates[] = (string) $billingImport;
        }

        $clientAt = DB::table('crm.clients')->where('workspace_id', $workspaceId)->whereNull('import_run_id')->min('created_at');
        $quoteAt = DB::table('billing.quotes')->where('workspace_id', $workspaceId)->where('is_historical_import', false)->min('created_at');
        $invoiceAt = DB::table('billing.invoices')->where('workspace_id', $workspaceId)->where('is_historical_import', false)->min('created_at');
        $documentAt = $this->earliest([$quoteAt, $invoiceAt]);
        if ($clientAt !== null && $documentAt !== null) {
            $candidates[] = max((string) $clientAt, $documentAt);
        }

        return $this->earliest($candidates);
    }

    /** @return array{at: string|null, path: string|null} */
    private function firstValue(object $participant): array
    {
        $workspaceId = (string) $participant->workspace_id;
        $analysisAt = DB::table('business_health.assessments')
            ->where('workspace_id', $workspaceId)
            ->where('assessment_status', 'Available')
            ->min('assessed_at');
        $quoteAt = DB::table('billing.quotes')->where('workspace_id', $workspaceId)->where('is_historical_import', false)->whereNotNull('sent_at')->min('sent_at');
        $invoiceAt = DB::table('billing.invoices')->where('workspace_id', $workspaceId)->where('is_historical_import', false)->whereNotNull('sent_at')->min('sent_at');
        $documentAt = $this->earliest([$quoteAt, $invoiceAt]);

        if ($analysisAt === null && $documentAt === null) {
            return ['at' => null, 'path' => null];
        }
        if ($analysisAt !== null && ($documentAt === null || (string) $analysisAt <= $documentAt)) {
            return ['at' => (string) $analysisAt, 'path' => 'Analysis'];
        }

        return ['at' => $documentAt, 'path' => 'Document'];
    }

    private function reusedAt(string $workspaceId, string $firstValueAt): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT MIN(action_at) AS reused_at
            FROM (
                SELECT created_at AS action_at FROM crm.clients WHERE workspace_id = ?
                UNION ALL SELECT updated_at FROM crm.opportunities WHERE workspace_id = ?
                UNION ALL SELECT updated_at FROM billing.quotes WHERE workspace_id = ? AND is_historical_import = false
                UNION ALL SELECT updated_at FROM billing.invoices WHERE workspace_id = ? AND is_historical_import = false
                UNION ALL SELECT created_at FROM billing.payments WHERE workspace_id = ? AND is_historical_import = false
                UNION ALL SELECT created_at FROM crm.activities WHERE workspace_id = ?
            ) AS actions
            WHERE action_at > ?::timestamptz
              AND (action_at AT TIME ZONE 'UTC')::date > (?::timestamptz AT TIME ZONE 'UTC')::date
            SQL,
            [$workspaceId, $workspaceId, $workspaceId, $workspaceId, $workspaceId, $workspaceId, $firstValueAt, $firstValueAt],
        );

        return $row?->reused_at !== null ? (string) $row->reused_at : null;
    }

    private function decisionAt(string $participantId): ?string
    {
        $value = DB::table('operations.pricing_observations')->where('participant_id', $participantId)->value('observed_on');

        return $value !== null ? (string) $value.' 00:00:00+00' : null;
    }

    /** @param array<int, mixed> $values */
    private function earliest(array $values): ?string
    {
        $available = array_values(array_map('strval', array_filter($values, static fn (mixed $value): bool => $value !== null)));
        sort($available);

        return $available[0] ?? null;
    }
}
