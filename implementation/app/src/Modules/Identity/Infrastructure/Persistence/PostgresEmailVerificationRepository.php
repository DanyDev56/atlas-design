<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Infrastructure\Persistence;

use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class PostgresEmailVerificationRepository
{
    public function createToken(
        string $userId,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?string $deliverySecret = null,
    ): string {
        $id = UuidGenerator::generate();

        DB::table('identity.email_verification_tokens')->insert([
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'delivery_secret' => $deliverySecret !== null ? Crypt::encryptString($deliverySecret) : null,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
            'created_at' => now()->toIso8601String(),
        ]);

        return $id;
    }

    /** @return array{email: string, display_name: string, token: string, expires_at: string}|null */
    public function findDeliveryContext(string $tokenId, string $userId): ?array
    {
        $row = DB::table('identity.email_verification_tokens as token')
            ->join('identity.users as user', 'user.id', '=', 'token.user_id')
            ->where('token.id', $tokenId)
            ->where('token.user_id', $userId)
            ->whereNull('token.consumed_at')
            ->where('token.expires_at', '>', now())
            ->first(['user.email', 'user.display_name', 'token.delivery_secret', 'token.expires_at']);

        if ($row === null || $row->delivery_secret === null) {
            return null;
        }

        return [
            'email' => (string) $row->email,
            'display_name' => (string) $row->display_name,
            'token' => Crypt::decryptString((string) $row->delivery_secret),
            'expires_at' => (string) $row->expires_at,
        ];
    }

    public function discardDeliverySecret(string $tokenId): void
    {
        DB::table('identity.email_verification_tokens')
            ->where('id', $tokenId)
            ->update(['delivery_secret' => null]);
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
            ->update([
                'consumed_at' => now()->toIso8601String(),
                'delivery_secret' => null,
            ]);

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
