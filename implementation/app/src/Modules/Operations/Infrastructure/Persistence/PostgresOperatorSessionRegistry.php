<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresOperatorSessionRegistry
{
    /** @return array{items: list<array<string, int|string|bool|null>>, total: int, page: int, per_page: int, total_pages: int} */
    public function page(string $status, int $page, int $perPage, string $currentSessionId, \DateTimeImmutable $now): array
    {
        $statusExpression = "CASE WHEN sessions.status = 'Revoked' THEN 'Revoked' WHEN sessions.expires_at <= ? THEN 'Expired' ELSE 'Active' END";
        $query = DB::table('operations.operator_sessions as sessions')
            ->select([
                'sessions.id', 'sessions.reference', 'sessions.user_id', 'sessions.authentication_strength',
                'sessions.mfa_verified_at', 'sessions.step_up_at', 'sessions.created_at', 'sessions.expires_at',
                'sessions.revoked_at', 'sessions.revision',
            ]);

        if ($status === 'Active') {
            $query->where('sessions.status', 'Active')->where('sessions.expires_at', '>', $now->format('Y-m-d H:i:sP'));
        } elseif ($status === 'Expired') {
            $query->where('sessions.status', 'Active')->where('sessions.expires_at', '<=', $now->format('Y-m-d H:i:sP'));
        } elseif ($status === 'Revoked') {
            $query->where('sessions.status', 'Revoked');
        }

        $total = (clone $query)->count();
        $rows = $query->selectRaw($statusExpression.' AS effective_status', [$now->format('Y-m-d H:i:sP')])
            ->orderByDesc('sessions.created_at')->forPage($page, $perPage)->get();
        $stepUpMinutes = max(1, min(30, (int) config('operations.backoffice.step_up_minutes', 10)));
        $items = $rows->map(function (object $row) use ($currentSessionId, $now, $stepUpMinutes): array {
            $stepUpExpiresAt = $row->step_up_at !== null
                ? (new \DateTimeImmutable((string) $row->step_up_at))->modify('+'.$stepUpMinutes.' minutes')
                : null;

            return [
                'reference' => (string) $row->reference,
                'operator_reference' => $this->opaqueReference((string) $row->user_id),
                'status' => (string) $row->effective_status,
                'current' => hash_equals((string) $row->id, $currentSessionId),
                'authentication_strength' => (string) $row->authentication_strength,
                'mfa_verified' => $row->mfa_verified_at !== null,
                'step_up_active' => $stepUpExpiresAt !== null && $stepUpExpiresAt > $now,
                'created_at' => (string) $row->created_at,
                'expires_at' => (string) $row->expires_at,
                'revoked_at' => $row->revoked_at !== null ? (string) $row->revoked_at : null,
                'revision' => (int) $row->revision,
            ];
        })->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function opaqueReference(string $userId): string
    {
        return 'OPR-'.strtoupper(substr(hash_hmac('sha256', $userId, (string) config('app.key', 'atlas-operations')), 0, 10));
    }
}
