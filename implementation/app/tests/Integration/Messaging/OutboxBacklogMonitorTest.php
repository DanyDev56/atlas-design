<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging;

use Atlas\Platform\Messaging\Infrastructure\OutboxBacklogMonitor;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Integration\IntegrationTestCase;

final class OutboxBacklogMonitorTest extends IntegrationTestCase
{
    public function test_logs_warning_when_pending_count_exceeds_threshold(): void
    {
        Config::set('platform.outbox.backlog_warning_threshold', 2);

        for ($i = 0; $i < 3; $i++) {
            DB::table('platform.outbox_messages')->insert([
                'id' => UuidGenerator::generate(),
                'event_id' => UuidGenerator::generate(),
                'event_type' => 'test.backlog',
                'payload' => json_encode(['index' => $i], JSON_THROW_ON_ERROR),
                'occurred_at' => now()->toIso8601String(),
                'correlation_id' => null,
                'causation_id' => null,
                'schema_version' => 1,
                'created_at' => now()->subMinutes(5)->toIso8601String(),
            ]);
        }

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        app(OutboxBacklogMonitor::class)->reportAfterProcessing(0);

        $warning = collect($logged)->first(
            fn (MessageLogged $event): bool => $event->level === 'warning'
                && $event->message === 'Outbox backlog above threshold',
        );

        $this->assertNotNull($warning);
        $this->assertSame(3, $warning->context['outbox.pending_count'] ?? null);
    }

    public function test_logs_snapshot_when_messages_remain_below_threshold(): void
    {
        Config::set('platform.outbox.backlog_warning_threshold', 25);

        DB::table('platform.outbox_messages')->insert([
            'id' => UuidGenerator::generate(),
            'event_id' => UuidGenerator::generate(),
            'event_type' => 'test.backlog',
            'payload' => json_encode(['index' => 0], JSON_THROW_ON_ERROR),
            'occurred_at' => now()->toIso8601String(),
            'correlation_id' => null,
            'causation_id' => null,
            'schema_version' => 1,
            'created_at' => now()->toIso8601String(),
        ]);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        app(OutboxBacklogMonitor::class)->reportAfterProcessing(1);

        $snapshot = collect($logged)->first(
            fn (MessageLogged $event): bool => $event->level === 'info'
                && $event->message === 'Outbox backlog snapshot',
        );

        $this->assertNotNull($snapshot);
        $this->assertSame(1, $snapshot->context['outbox.pending_count'] ?? null);
        $this->assertSame(1, $snapshot->context['outbox.processed_in_batch'] ?? null);
    }

    public function test_posts_webhook_when_configured_and_threshold_exceeded(): void
    {
        Config::set('platform.outbox.backlog_warning_threshold', 1);
        Config::set('platform.outbox.alert_webhook_url', 'https://alerts.test/outbox');

        DB::table('platform.outbox_messages')->insert([
            'id' => UuidGenerator::generate(),
            'event_id' => UuidGenerator::generate(),
            'event_type' => 'test.backlog',
            'payload' => json_encode(['index' => 0], JSON_THROW_ON_ERROR),
            'occurred_at' => now()->toIso8601String(),
            'correlation_id' => null,
            'causation_id' => null,
            'schema_version' => 1,
            'created_at' => now()->toIso8601String(),
        ]);

        Http::fake();

        app(OutboxBacklogMonitor::class)->reportAfterProcessing(0);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://alerts.test/outbox'
                && ($request['alert'] ?? null) === 'outbox_backlog_above_threshold';
        });
    }
}
