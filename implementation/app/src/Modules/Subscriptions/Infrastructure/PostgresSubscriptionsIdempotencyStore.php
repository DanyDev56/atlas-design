<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure;

use Illuminate\Support\Facades\DB;

final class PostgresSubscriptionsIdempotencyStore
{
    /** @return array<string, mixed>|null */
    public function find(string $scope, string $key): ?array
    {
        $row = DB::table('subscriptions.idempotency_keys')
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fingerprint' => (string) $row->fingerprint,
            'response_payload' => json_decode((string) $row->response_payload, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /** @param array<string, string> $response */
    public function store(string $scope, string $key, string $fingerprint, array $response): void
    {
        DB::table('subscriptions.idempotency_keys')->insert([
            'scope' => $scope,
            'key' => $key,
            'fingerprint' => $fingerprint,
            'response_payload' => json_encode($response, JSON_THROW_ON_ERROR),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
