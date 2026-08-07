<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Modules\Identity\Domain\SessionId;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Platform\Support\UuidGenerator;
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
