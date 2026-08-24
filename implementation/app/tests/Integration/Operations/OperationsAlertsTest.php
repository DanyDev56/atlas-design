<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Integration\IntegrationTestCase;

final class OperationsAlertsTest extends IntegrationTestCase
{
    public function test_a_firing_alert_is_not_repeated_and_its_resolution_is_notified(): void
    {
        Config::set('operations.alerts.webhook_url', 'https://alerts.example.test/atlas');
        Config::set('operations.http.minimum_requests', 1);
        Config::set('operations.http.error_rate_threshold_percent', 10);
        Config::set('operations.alerts.repeat_minutes', 60);
        Http::fake(['https://alerts.example.test/*' => Http::response([], 202)]);
        $now = now('UTC');
        DB::table('operations.http_red_minute_buckets')->insert([
            'bucket_started_at' => $now->copy()->startOfMinute(),
            'method' => 'GET',
            'route_template' => '/api/test/{id}',
            'status_class' => '5xx',
            'request_count' => 1,
            'error_count' => 1,
            'duration_sum_ms' => 12,
            'duration_max_ms' => 12,
            'updated_at' => $now,
        ]);

        $this->artisan('atlas:operations:evaluate-alerts')->assertSuccessful();
        $this->artisan('atlas:operations:evaluate-alerts')->assertSuccessful();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['alert'] === 'http_high_error_rate' && $request['state'] === 'Firing');

        DB::table('operations.http_red_minute_buckets')->delete();
        $this->artisan('atlas:operations:evaluate-alerts')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['alert'] === 'http_high_error_rate' && $request['state'] === 'Resolved');
        $this->assertDatabaseHas('operations.alert_states', [
            'alert_key' => 'http_high_error_rate',
            'state' => 'Healthy',
            'last_notification_state' => 'Resolved',
        ]);
    }

    public function test_alert_context_remains_aggregated_and_contains_no_route_or_provider_reference(): void
    {
        Config::set('operations.alerts.webhook_url', 'https://alerts.example.test/atlas');
        Config::set('operations.http.minimum_requests', 1);
        Http::fake(['https://alerts.example.test/*' => Http::response([], 202)]);
        DB::table('operations.http_red_minute_buckets')->insert([
            'bucket_started_at' => now('UTC')->startOfMinute(),
            'method' => 'POST',
            'route_template' => '/api/private/{secret}',
            'status_class' => '5xx',
            'request_count' => 2,
            'error_count' => 2,
            'duration_sum_ms' => 20,
            'duration_max_ms' => 10,
            'updated_at' => now('UTC'),
        ]);

        $this->artisan('atlas:operations:evaluate-alerts')->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

            return $payload['alert'] === 'http_high_error_rate'
                && ! str_contains($encoded, '/api/private')
                && ! str_contains($encoded, 'secret');
        });
    }
}
