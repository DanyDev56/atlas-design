<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresOperatorSessionRepository
{
    public function create(
        string $id,
        string $reference,
        string $userId,
        string $grantId,
        string $tokenHash,
        string $authenticationStrength,
        ?\DateTimeImmutable $mfaVerifiedAt,
        ?\DateTimeImmutable $stepUpAt,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): void {
        DB::table('operations.operator_sessions')->insert([
            'id' => $id,
            'reference' => $reference,
            'user_id' => $userId,
            'grant_id' => $grantId,
            'token_hash' => $tokenHash,
            'status' => 'Active',
            'authentication_strength' => $authenticationStrength,
            'mfa_verified_at' => $mfaVerifiedAt?->format('Y-m-d H:i:sP'),
            'step_up_at' => $stepUpAt?->format('Y-m-d H:i:sP'),
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
                'sessions.authentication_strength',
                'sessions.mfa_verified_at',
                'sessions.step_up_at',
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
            'authentication_strength' => (string) $row->authentication_strength,
            'mfa_verified_at' => $row->mfa_verified_at !== null ? (string) $row->mfa_verified_at : null,
            'step_up_at' => $row->step_up_at !== null ? (string) $row->step_up_at : null,
            'permissions' => is_array($permissions) ? array_values(array_map('strval', $permissions)) : [],
        ];
    }

    public function elevate(
        string $sessionId,
        string $userId,
        string $authenticationStrength,
        \DateTimeImmutable $now,
    ): bool {
        return DB::table('operations.operator_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->where('expires_at', '>', $now->format('Y-m-d H:i:sP'))
            ->update([
                'authentication_strength' => $authenticationStrength,
                'mfa_verified_at' => $now->format('Y-m-d H:i:sP'),
                'step_up_at' => $now->format('Y-m-d H:i:sP'),
            ]) === 1;
    }

    public function revoke(string $sessionId, string $userId, \DateTimeImmutable $now): bool
    {
        return DB::table('operations.operator_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'revision' => DB::raw('revision + 1'),
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
                'revision' => DB::raw('revision + 1'),
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

    public static function generateReference(): string
    {
        return 'SES-'.strtoupper(bin2hex(random_bytes(6)));
    }
}
