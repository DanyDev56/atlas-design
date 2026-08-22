<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresAccountRecoveryRepository
{
    public function createToken(string $userId, string $tokenHash, \DateTimeImmutable $expiresAt): string
    {
        $this->invalidateUnused($userId);

        $id = UuidGenerator::generate();
        DB::table('identity.account_recovery_tokens')->insert([
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /** @return array{id: string, user_id: string}|null */
    public function consumeValidToken(string $plainToken): ?array
    {
        $hash = self::hashToken($plainToken);
        $row = DB::table('identity.account_recovery_tokens')
            ->where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        DB::table('identity.account_recovery_tokens')
            ->where('id', $row->id)
            ->update(['consumed_at' => now()->toIso8601String()]);

        return [
            'id' => (string) $row->id,
            'user_id' => (string) $row->user_id,
        ];
    }

    public function invalidateUnused(string $userId): void
    {
        DB::table('identity.account_recovery_tokens')
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()->toIso8601String()]);
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
