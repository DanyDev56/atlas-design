<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostgresOperatorMfaRepository
{
    /** @param list<string> $recoveryCodeHashes */
    public function enroll(
        string $userId,
        string $secretCiphertext,
        array $recoveryCodeHashes,
        \DateTimeImmutable $now,
    ): void {
        DB::table('operations.operator_mfa_credentials')->updateOrInsert(
            ['user_id' => $userId],
            [
                'id' => (string) Str::uuid(),
                'secret_ciphertext' => $secretCiphertext,
                'recovery_code_hashes' => json_encode($recoveryCodeHashes, JSON_THROW_ON_ERROR),
                'status' => 'Active',
                'last_used_timestep' => null,
                'enrolled_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
                'disabled_at' => null,
            ],
        );
    }

    /** @return array{secret_ciphertext: string, recovery_code_hashes: list<string>, last_used_timestep: int|null}|null */
    public function findActiveForUser(string $userId): ?array
    {
        $row = DB::table('operations.operator_mfa_credentials')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->first();

        if ($row === null) {
            return null;
        }

        $hashes = is_array($row->recovery_code_hashes)
            ? $row->recovery_code_hashes
            : json_decode((string) $row->recovery_code_hashes, true, 512, JSON_THROW_ON_ERROR);

        return [
            'secret_ciphertext' => (string) $row->secret_ciphertext,
            'recovery_code_hashes' => is_array($hashes) ? array_values(array_map('strval', $hashes)) : [],
            'last_used_timestep' => $row->last_used_timestep !== null ? (int) $row->last_used_timestep : null,
        ];
    }

    public function claimTimestep(string $userId, int $timestep, \DateTimeImmutable $now): bool
    {
        return DB::table('operations.operator_mfa_credentials')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->where(function ($query) use ($timestep): void {
                $query->whereNull('last_used_timestep')
                    ->orWhere('last_used_timestep', '<', $timestep);
            })
            ->update([
                'last_used_timestep' => $timestep,
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]) === 1;
    }

    public function consumeRecoveryCode(string $userId, string $hash, \DateTimeImmutable $now): bool
    {
        return DB::transaction(function () use ($userId, $hash, $now): bool {
            $row = DB::table('operations.operator_mfa_credentials')
                ->where('user_id', $userId)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return false;
            }

            $hashes = is_array($row->recovery_code_hashes)
                ? $row->recovery_code_hashes
                : json_decode((string) $row->recovery_code_hashes, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($hashes)) {
                return false;
            }

            $remaining = array_values(array_filter(
                array_map('strval', $hashes),
                static fn (string $candidate): bool => ! hash_equals($candidate, $hash),
            ));
            if (count($remaining) === count($hashes)) {
                return false;
            }

            DB::table('operations.operator_mfa_credentials')
                ->where('user_id', $userId)
                ->update([
                    'recovery_code_hashes' => json_encode($remaining, JSON_THROW_ON_ERROR),
                    'updated_at' => $now->format('Y-m-d H:i:sP'),
                ]);

            return true;
        });
    }

    public function disable(string $userId, \DateTimeImmutable $now): bool
    {
        return DB::table('operations.operator_mfa_credentials')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Disabled',
                'secret_ciphertext' => '',
                'recovery_code_hashes' => json_encode([], JSON_THROW_ON_ERROR),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
                'disabled_at' => $now->format('Y-m-d H:i:sP'),
            ]) === 1;
    }
}
