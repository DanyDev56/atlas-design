<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Infrastructure;

use Illuminate\Support\Facades\DB;

final class PostgresAdvisorIdempotencyStore
{
    /** @return array<string, mixed>|null */
    public function find(string $scope, string $key): ?array
    {
        $row = DB::table('advisor.idempotency_keys')
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fingerprint' => $row->fingerprint,
            'response_payload' => json_decode($row->response_payload ?? 'null', true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** @param array<string, mixed> $response */
    public function store(string $scope, string $key, string $fingerprint, array $response): void
    {
        DB::table('advisor.idempotency_keys')->insert([
            'scope' => $scope,
            'key' => $key,
            'fingerprint' => $fingerprint,
            'response_payload' => json_encode($response, JSON_THROW_ON_ERROR),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
