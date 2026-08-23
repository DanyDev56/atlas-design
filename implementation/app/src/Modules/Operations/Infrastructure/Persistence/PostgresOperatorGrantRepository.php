<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostgresOperatorGrantRepository
{
    /** @param list<string> $permissions */
    public function provision(
        string $userId,
        array $permissions,
        ?\DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): array {
        $existing = DB::table('operations.operator_grants')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            $version = (int) $existing->version + 1;
            DB::table('operations.operator_grants')
                ->where('id', $existing->id)
                ->update([
                    'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                    'status' => 'Active',
                    'expires_at' => $expiresAt?->format('Y-m-d H:i:sP'),
                    'version' => $version,
                    'updated_at' => $now->format('Y-m-d H:i:sP'),
                    'revoked_at' => null,
                ]);

            return [
                'id' => (string) $existing->id,
                'user_id' => $userId,
                'permissions' => $permissions,
                'status' => 'Active',
                'expires_at' => $expiresAt?->format(DATE_ATOM),
                'version' => $version,
            ];
        }

        $id = (string) Str::uuid();
        DB::table('operations.operator_grants')->insert([
            'id' => $id,
            'user_id' => $userId,
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
            'status' => 'Active',
            'expires_at' => $expiresAt?->format('Y-m-d H:i:sP'),
            'version' => 1,
            'created_at' => $now->format('Y-m-d H:i:sP'),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ]);

        return [
            'id' => $id,
            'user_id' => $userId,
            'permissions' => $permissions,
            'status' => 'Active',
            'expires_at' => $expiresAt?->format(DATE_ATOM),
            'version' => 1,
        ];
    }

    public function findActiveForUser(string $userId, \DateTimeImmutable $now): ?array
    {
        $row = DB::table('operations.operator_grants')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now->format('Y-m-d H:i:sP'));
            })
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'user_id' => (string) $row->user_id,
            'permissions' => $this->decodePermissions($row->permissions),
            'status' => (string) $row->status,
            'expires_at' => $row->expires_at !== null ? (string) $row->expires_at : null,
            'version' => (int) $row->version,
        ];
    }

    public function revokeForUser(string $userId, \DateTimeImmutable $now): bool
    {
        return DB::table('operations.operator_grants')
            ->where('user_id', $userId)
            ->where('status', 'Active')
            ->update([
                'status' => 'Revoked',
                'version' => DB::raw('version + 1'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
                'revoked_at' => $now->format('Y-m-d H:i:sP'),
            ]) > 0;
    }

    /** @return list<string> */
    private function decodePermissions(mixed $value): array
    {
        $permissions = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($permissions) ? array_values(array_map('strval', $permissions)) : [];
    }
}
