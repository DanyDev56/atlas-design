<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresEmailVerificationRepository
{
    public function createToken(string $userId, string $tokenHash, \DateTimeImmutable $expiresAt): string
    {
        $id = UuidGenerator::generate();

        DB::table('identity.email_verification_tokens')->insert([
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    public function consumeValidToken(string $userId, string $plainToken): bool
    {
        $hash = hash('sha256', $plainToken);

        $row = DB::table('identity.email_verification_tokens')
            ->where('user_id', $userId)
            ->where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return false;
        }

        DB::table('identity.email_verification_tokens')
            ->where('id', $row->id)
            ->update(['consumed_at' => now()->toIso8601String()]);

        return true;
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
