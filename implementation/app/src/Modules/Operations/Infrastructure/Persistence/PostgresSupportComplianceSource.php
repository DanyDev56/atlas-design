<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Atlas\Modules\Operations\Contracts\SupportComplianceSource;
use Illuminate\Support\Facades\DB;

final class PostgresSupportComplianceSource implements SupportComplianceSource
{
    public function supportSnapshot(\DateTimeImmutable $now): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE status NOT IN ('Resolved', 'Closed'))::int AS open_count,
                COUNT(*) FILTER (WHERE status NOT IN ('Resolved', 'Closed') AND response_due_at < ?)::int AS overdue_count,
                COUNT(*) FILTER (WHERE status NOT IN ('Resolved', 'Closed') AND severity IN ('P0', 'P1'))::int AS urgent_count
            FROM operations.support_cases
            SQL,
            [$now->format('Y-m-d H:i:sP')],
        );

        return [
            'open_count' => (int) ($row->open_count ?? 0),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'urgent_count' => (int) ($row->urgent_count ?? 0),
        ];
    }

    public function dataRequestSnapshot(\DateTimeImmutable $now): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE status NOT IN ('Rejected', 'Closed'))::int AS open_count,
                COUNT(*) FILTER (WHERE status NOT IN ('Rejected', 'Closed') AND due_at < ?)::int AS overdue_count,
                COUNT(*) FILTER (WHERE identity_verified_at IS NULL)::int AS verification_pending_count
            FROM operations.data_requests
            SQL,
            [$now->format('Y-m-d H:i:sP')],
        );

        return [
            'open_count' => (int) ($row->open_count ?? 0),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'verification_pending_count' => (int) ($row->verification_pending_count ?? 0),
        ];
    }

    public function complianceSnapshot(): array
    {
        $published = (int) DB::table('operations.policy_versions')->where('lifecycle', 'Published')->count();
        $proofs = (int) DB::table('operations.policy_acknowledgements')->count();
        $activeConsents = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*)::int AS active_count
            FROM (
                SELECT DISTINCT ON (user_id, purpose) decision
                FROM operations.research_consent_events
                ORDER BY user_id, purpose, occurred_at DESC, recorded_at DESC
            ) latest
            WHERE decision = 'Granted'
            SQL,
        );

        return [
            'published_policy_count' => $published,
            'policy_proof_count' => $proofs,
            'active_consent_count' => (int) ($activeConsents->active_count ?? 0),
        ];
    }

    public function supportPage(string $status, string $severity, int $page, int $perPage): array
    {
        $query = DB::table('operations.support_cases as support')
            ->select([
                'support.reference', 'support.workspace_id', 'support.requester_user_id', 'support.category',
                'support.severity', 'support.status', 'support.requester_verified', 'support.ownership_verified',
                'support.summary_code', 'support.response_due_at', 'support.opened_at', 'support.resolved_at',
                'support.assigned_operator_user_id', 'support.revision',
                DB::raw('(SELECT COUNT(*) FROM operations.support_case_events event WHERE event.support_case_id = support.id)::int AS event_count'),
            ]);
        if ($status !== 'All') {
            $query->where('support.status', $status);
        }
        if ($severity !== 'All') {
            $query->where('support.severity', $severity);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByRaw("CASE support.severity WHEN 'P0' THEN 0 WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 ELSE 3 END")
            ->orderBy('support.response_due_at')->forPage($page, $perPage)->get();
        $items = $rows->map(fn (object $row): array => [
            'reference' => (string) $row->reference,
            'workspace_reference' => $this->opaqueReference('WS', (string) $row->workspace_id),
            'requester_reference' => $this->opaqueReference('USR', (string) $row->requester_user_id),
            'category' => (string) $row->category,
            'severity' => (string) $row->severity,
            'status' => (string) $row->status,
            'assigned' => $row->assigned_operator_user_id !== null,
            'revision' => (int) $row->revision,
            'requester_verified' => (bool) $row->requester_verified,
            'ownership_verified' => (bool) $row->ownership_verified,
            'summary_code' => (string) $row->summary_code,
            'response_due_at' => (string) $row->response_due_at,
            'opened_at' => (string) $row->opened_at,
            'resolved_at' => $row->resolved_at !== null ? (string) $row->resolved_at : null,
            'event_count' => (int) $row->event_count,
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function dataRequestPage(string $status, string $type, int $page, int $perPage): array
    {
        $query = DB::table('operations.data_requests as request')
            ->join('operations.support_cases as support', 'support.id', '=', 'request.support_case_id')
            ->leftJoin('operations.data_exports as export', 'export.data_request_id', '=', 'request.id')
            ->leftJoin('operations.data_export_artifacts as artifact', 'artifact.data_export_id', '=', 'export.id')
            ->select([
                'request.reference', 'support.reference as support_reference', 'request.workspace_id', 'request.requester_user_id', 'request.request_type', 'request.status',
                'request.identity_verified_at', 'request.ownership_verified_at', 'request.due_at', 'request.decision_code', 'request.delivery_expires_at', 'request.created_at',
                'export.reference as export_reference', 'export.scope as export_scope', 'export.status as export_status',
                'export.requested_at as export_requested_at', 'export.ready_at as export_ready_at', 'export.expires_at as export_expires_at',
                'artifact.byte_size as export_byte_size',
            ]);
        if ($status !== 'All') {
            $query->where('request.status', $status);
        }
        if ($type !== 'All') {
            $query->where('request.request_type', $type);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('request.due_at')->forPage($page, $perPage)->get();
        $items = $rows->map(fn (object $row): array => [
            'reference' => (string) $row->reference,
            'support_reference' => (string) $row->support_reference,
            'workspace_reference' => $this->opaqueReference('WS', (string) $row->workspace_id),
            'requester_reference' => $this->opaqueReference('USR', (string) $row->requester_user_id),
            'request_type' => (string) $row->request_type,
            'status' => (string) $row->status,
            'identity_verified' => $row->identity_verified_at !== null,
            'ownership_verified' => $row->ownership_verified_at !== null,
            'due_at' => (string) $row->due_at,
            'decision_code' => $row->decision_code !== null ? (string) $row->decision_code : null,
            'delivery_expires_at' => $row->delivery_expires_at !== null ? (string) $row->delivery_expires_at : null,
            'created_at' => (string) $row->created_at,
            'export' => $row->export_reference === null ? null : [
                'reference' => (string) $row->export_reference,
                'scope' => (string) $row->export_scope,
                'status' => (string) $row->export_status,
                'requested_at' => (string) $row->export_requested_at,
                'ready_at' => $row->export_ready_at !== null ? (string) $row->export_ready_at : null,
                'expires_at' => $row->export_expires_at !== null ? (string) $row->export_expires_at : null,
                'byte_size' => $row->export_byte_size !== null ? (int) $row->export_byte_size : null,
            ],
        ])->all();

        return $this->page($items, $total, $page, $perPage);
    }

    public function policyPage(int $page, int $perPage): array
    {
        $query = DB::table('operations.policy_versions as policy')->select([
            'policy.id', 'policy.document_kind', 'policy.version', 'policy.lifecycle', 'policy.effective_at', 'policy.recorded_at',
            DB::raw('(SELECT COUNT(*) FROM operations.policy_acknowledgements proof WHERE proof.policy_version_id = policy.id)::int AS proof_count'),
        ]);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('recorded_at')->forPage($page, $perPage)->get();
        $items = $rows->map(static fn (object $row): array => [
            'document_kind' => (string) $row->document_kind,
            'version' => (string) $row->version,
            'lifecycle' => (string) $row->lifecycle,
            'effective_at' => $row->effective_at !== null ? (string) $row->effective_at : null,
            'recorded_at' => (string) $row->recorded_at,
            'proof_count' => (int) $row->proof_count,
        ])->all();
        $consents = ['Interview' => 0, 'Recording' => 0, 'PublicQuote' => 0];
        $rows = DB::select(
            <<<'SQL'
            SELECT purpose, COUNT(*)::int AS count
            FROM (
                SELECT DISTINCT ON (user_id, purpose) purpose, decision
                FROM operations.research_consent_events
                ORDER BY user_id, purpose, occurred_at DESC, recorded_at DESC
            ) latest
            WHERE decision = 'Granted'
            GROUP BY purpose
            SQL,
        );
        foreach ($rows as $row) {
            $consents[(string) $row->purpose] = (int) $row->count;
        }

        return [...$this->page($items, $total, $page, $perPage), 'consent_counts' => $consents];
    }

    private function opaqueReference(string $prefix, string $value): string
    {
        return $prefix.'-'.strtoupper(substr(hash_hmac('sha256', $value, (string) config('app.key', 'atlas-operations')), 0, 10));
    }

    /** @param list<array<string, int|string|bool|null>> $items */
    private function page(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
