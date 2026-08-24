<?php

declare(strict_types=1);

namespace Atlas\Modules\Operations\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;

final class PostgresHttpRedMetricsRecorder
{
    public function record(string $method, string $routeTemplate, int $statusCode, int $durationMs): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $bucket = $now->setTime((int) $now->format('H'), (int) $now->format('i'));
        $method = substr(strtoupper($method), 0, 8);
        $routeTemplate = substr($routeTemplate, 0, 180);
        $statusClass = max(1, min(5, intdiv($statusCode, 100))).'xx';
        $errorCount = $statusCode >= 500 ? 1 : 0;
        $durationMs = max(0, min(3_600_000, $durationMs));
        $timestamp = $now->format('Y-m-d H:i:sP');

        DB::statement(<<<'SQL'
            INSERT INTO operations.http_red_minute_buckets (
                bucket_started_at, method, route_template, status_class,
                request_count, error_count, duration_sum_ms, duration_max_ms, updated_at
            ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)
            ON CONFLICT (bucket_started_at, method, route_template, status_class)
            DO UPDATE SET
                request_count = operations.http_red_minute_buckets.request_count + 1,
                error_count = operations.http_red_minute_buckets.error_count + EXCLUDED.error_count,
                duration_sum_ms = operations.http_red_minute_buckets.duration_sum_ms + EXCLUDED.duration_sum_ms,
                duration_max_ms = GREATEST(operations.http_red_minute_buckets.duration_max_ms, EXCLUDED.duration_max_ms),
                updated_at = EXCLUDED.updated_at
            SQL,
            [
                $bucket->format('Y-m-d H:i:sP'),
                $method,
                $routeTemplate,
                $statusClass,
                $errorCount,
                $durationMs,
                $durationMs,
                $timestamp,
            ],
        );
    }
}
