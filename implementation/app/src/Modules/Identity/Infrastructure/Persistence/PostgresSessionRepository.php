<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Modules\Identity\Domain\SessionId;
use Atlas\Modules\Identity\Domain\UserId;
use Illuminate\Support\Facades\DB;

final class PostgresSessionRepository
{
    public function create(
        SessionId $sessionId,
        UserId $userId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
    ): void {
        DB::table('identity.sessions')->insert([
            'id' => $sessionId->value,
            'user_id' => $userId->value,
            'token_hash' => $tokenHash,
            'status' => 'Active',
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => $createdAt->format('Y-m-d H:i:sP'),
        ]);
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $row = DB::table('identity.sessions')
            ->where('token_hash', $tokenHash)
            ->where('status', 'Active')
            ->where('expires_at', '>', now())
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(SessionId $sessionId): ?array
    {
        $row = DB::table('identity.sessions')
            ->where('id', $sessionId->value)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function revoke(SessionId $sessionId, \DateTimeImmutable $revokedAt): void
    {
        DB::table('identity.sessions')
            ->where('id', $sessionId->value)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'revoked_at' => $revokedAt->format('Y-m-d H:i:sP'),
                'elevation_status' => null,
                'elevation_scope' => null,
                'elevation_permissions' => null,
                'elevation_expires_at' => null,
            ]);
    }

    /** @param list<string> $permissions */
    public function activateElevation(
        SessionId $sessionId,
        array $permissions,
        \DateTimeImmutable $expiresAt,
        int $version,
    ): void {
        DB::table('identity.sessions')
            ->where('id', $sessionId->value)
            ->where('status', 'Active')
            ->update([
                'elevation_status' => 'Active',
                'elevation_scope' => 'PermissionScoped',
                'elevation_permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'elevation_expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
                'elevation_version' => $version,
            ]);
    }

    public function activeElevationExpiresAt(string $sessionId): ?\DateTimeImmutable
    {
        $row = DB::table('identity.sessions')
            ->where('id', $sessionId)
            ->where('status', 'Active')
            ->where('elevation_status', 'Active')
            ->first();

        if ($row === null || $row->elevation_expires_at === null) {
            return null;
        }

        $expiresAt = new \DateTimeImmutable((string) $row->elevation_expires_at);
        if ($expiresAt <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            return null;
        }

        return $expiresAt;
    }

    /** @return list<string> */
    public function activeElevationPermissions(string $sessionId): array
    {
        $row = DB::table('identity.sessions')
            ->where('id', $sessionId)
            ->where('status', 'Active')
            ->where('elevation_status', 'Active')
            ->first();

        if ($row === null || $row->elevation_expires_at === null) {
            return [];
        }

        $expiresAt = new \DateTimeImmutable((string) $row->elevation_expires_at);
        if ($expiresAt <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            return [];
        }

        $permissions = is_array($row->elevation_permissions)
            ? $row->elevation_permissions
            : json_decode((string) $row->elevation_permissions, true, 512, JSON_THROW_ON_ERROR);

        return is_array($permissions) ? array_values($permissions) : [];
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
