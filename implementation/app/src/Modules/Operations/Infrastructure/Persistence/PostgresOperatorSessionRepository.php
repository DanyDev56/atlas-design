<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresOperatorSessionRepository
{
    public function create(
        string $id,
        string $userId,
        string $grantId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): void {
        DB::table('operations.operator_sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'grant_id' => $grantId,
            'token_hash' => $tokenHash,
            'status' => 'Active',
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }

    public function findActiveByTokenHash(string $tokenHash, \DateTimeImmutable $now): ?array
    {
        $row = DB::table('operations.operator_sessions as sessions')
            ->join('operations.operator_grants as grants', 'grants.id', '=', 'sessions.grant_id')
            ->where('sessions.token_hash', $tokenHash)
            ->where('sessions.status', 'Active')
            ->where('sessions.expires_at', '>', $now->format('Y-m-d H:i:sP'))
            ->where('grants.status', 'Active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('grants.expires_at')
                    ->orWhere('grants.expires_at', '>', $now->format('Y-m-d H:i:sP'));
            })
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.grant_id',
                'sessions.expires_at',
                'grants.permissions',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $permissions = is_array($row->permissions)
            ? $row->permissions
            : json_decode((string) $row->permissions, true, 512, JSON_THROW_ON_ERROR);

        return [
            'id' => (string) $row->id,
            'user_id' => (string) $row->user_id,
            'grant_id' => (string) $row->grant_id,
            'expires_at' => (string) $row->expires_at,
            'permissions' => is_array($permissions) ? array_values(array_map('strval', $permissions)) : [],
        ];
    }

    public function revoke(string $sessionId, string $userId, \DateTimeImmutable $now): bool
    {
        return DB::table('operations.operator_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $now->format('Y-m-d H:i:sP'),
            ]) > 0;
    }

    public function revokeAllForUser(string $userId, \DateTimeImmutable $now): int
    {
        return DB::table('operations.operator_sessions')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $now->format('Y-m-d H:i:sP'),
            ]);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
