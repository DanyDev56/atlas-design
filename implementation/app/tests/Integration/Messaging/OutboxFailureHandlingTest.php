<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging;

use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\InboxStore;
use Atlas\Platform\Messaging\Infrastructure\OutboxBacklogMonitor;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Integration\IntegrationTestCase;

final class OutboxFailureHandlingTest extends IntegrationTestCase
{
    public function test_transient_failure_rolls_back_effect_and_respects_backoff(): void
    {
        $consumer = new ControllableOutboxConsumer;
        $eventId = $this->appendMessage('transient');
        $processor = $this->processor($consumer, maxAttempts: 3, retryBaseSeconds: 60);

        $this->assertSame(0, $processor->processPending(10));

        $row = DB::table('platform.outbox_messages')->where('event_id', $eventId)->first();

        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame(\RuntimeException::class, $row->last_error);
        $this->assertNull($row->failed_at);
        $this->assertGreaterThan(now()->getTimestamp(), strtotime((string) $row->available_at));
        $this->assertDatabaseMissing('platform.spike_consumer_effects', [
            'consumer_name' => 'test.failure.transient',
        ]);

        $this->assertSame(0, $processor->processPending(10));
        $this->assertSame(1, $consumer->calls['transient']);

        DB::table('platform.outbox_messages')
            ->where('event_id', $eventId)
            ->update(['available_at' => now()->subSecond()->toIso8601String()]);
        $consumer->failingModes = [];

        $this->assertSame(1, $processor->processPending(10));
        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $eventId,
            'attempts' => 1,
            'last_error' => null,
        ]);
        $this->assertNotNull(
            DB::table('platform.outbox_messages')->where('event_id', $eventId)->value('dispatched_at'),
        );
        $this->assertSame(
            1,
            (int) DB::table('platform.spike_consumer_effects')
                ->where('consumer_name', 'test.failure.transient')
                ->value('effect_count'),
        );
    }

    public function test_poison_message_is_dead_lettered_alerted_and_can_be_released(): void
    {
        Config::set('platform.outbox.alert_webhook_url', 'https://alerts.test/outbox');
        Http::fake();
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $consumer = new ControllableOutboxConsumer;
        $poisonEventId = $this->appendMessage('poison');
        $healthyEventId = $this->appendMessage('healthy');
        $processor = $this->processor($consumer, maxAttempts: 2, retryBaseSeconds: 60);

        $this->assertSame(1, $processor->processPending(10));
        $this->assertNotNull(
            DB::table('platform.outbox_messages')->where('event_id', $healthyEventId)->value('dispatched_at'),
        );

        DB::table('platform.outbox_messages')
            ->where('event_id', $poisonEventId)
            ->update(['available_at' => now()->subSecond()->toIso8601String()]);

        $this->assertSame(0, $processor->processPending(10));
        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $poisonEventId,
            'attempts' => 2,
            'last_error' => \RuntimeException::class,
        ]);
        $this->assertNotNull(
            DB::table('platform.outbox_messages')->where('event_id', $poisonEventId)->value('failed_at'),
        );
        Http::assertSent(
            fn ($request): bool => $request->url() === 'https://alerts.test/outbox'
                && ($request['alert'] ?? null) === 'outbox_message_dead_lettered'
                && ($request['context']['outbox.event_id'] ?? null) === $poisonEventId,
        );
        $serializedLogs = json_encode(
            array_map(
                fn (MessageLogged $event): array => [
                    'message' => $event->message,
                    'context' => $event->context,
                ],
                $logged,
            ),
            JSON_THROW_ON_ERROR,
        );
        $this->assertStringNotContainsString('provider-token', $serializedLogs);
        $this->assertStringNotContainsString('<script>', $serializedLogs);

        $this->assertSame(0, $processor->processPending(10));
        $this->assertSame(2, $consumer->calls['poison']);
        Http::assertSentCount(1);

        $this->artisan('atlas:outbox:retry', ['event-id' => $poisonEventId])
            ->expectsOutput("Outbox message {$poisonEventId} scheduled for retry.")
            ->assertSuccessful();

        $consumer->failingModes = [];

        $this->assertSame(1, $processor->processPending(10));
        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $poisonEventId,
            'attempts' => 0,
            'failed_at' => null,
        ]);
        $this->assertNotNull(
            DB::table('platform.outbox_messages')->where('event_id', $poisonEventId)->value('dispatched_at'),
        );
    }

    public function test_successful_consumer_is_not_replayed_when_a_later_consumer_retries(): void
    {
        $successfulConsumer = new ControllableOutboxConsumer('test.first', []);
        $transientConsumer = new ControllableOutboxConsumer('test.second', ['transient']);
        $eventId = $this->appendMessage('transient');
        $processor = $this->processor(
            [$successfulConsumer, $transientConsumer],
            maxAttempts: 3,
            retryBaseSeconds: 60,
        );

        $this->assertSame(0, $processor->processPending(10));
        $this->assertSame(1, $successfulConsumer->calls['transient']);
        $this->assertSame(1, $transientConsumer->calls['transient']);
        $this->assertDatabaseHas('platform.inbox_receipts', [
            'consumer_name' => 'test.first',
            'event_id' => $eventId,
        ]);
        $this->assertDatabaseMissing('platform.inbox_receipts', [
            'consumer_name' => 'test.second',
            'event_id' => $eventId,
        ]);
        $this->assertDatabaseHas('platform.spike_consumer_effects', [
            'consumer_name' => 'test.first.transient',
            'effect_count' => 1,
        ]);
        $this->assertDatabaseMissing('platform.spike_consumer_effects', [
            'consumer_name' => 'test.second.transient',
        ]);

        DB::table('platform.outbox_messages')
            ->where('event_id', $eventId)
            ->update(['available_at' => now()->subSecond()->toIso8601String()]);
        $transientConsumer->failingModes = [];

        $this->assertSame(1, $processor->processPending(10));
        $this->assertSame(1, $successfulConsumer->calls['transient']);
        $this->assertSame(2, $transientConsumer->calls['transient']);
        $this->assertDatabaseHas('platform.spike_consumer_effects', [
            'consumer_name' => 'test.second.transient',
            'effect_count' => 1,
        ]);
    }

    public function test_retry_command_rejects_invalid_or_active_event(): void
    {
        $this->artisan('atlas:outbox:retry', ['event-id' => 'not-a-uuid'])
            ->expectsOutput('The event-id argument must be a valid UUID.')
            ->assertExitCode(2);

        $eventId = $this->appendMessage('healthy');

        $this->artisan('atlas:outbox:retry', ['event-id' => $eventId])
            ->expectsOutput("No dead-lettered outbox message found for event {$eventId}.")
            ->assertFailed();
    }

    private function appendMessage(string $mode): string
    {
        $eventId = EventId::generate();

        app(OutboxWriter::class)->append(new OutgoingMessage(
            eventId: $eventId,
            eventType: 'test.failure',
            payload: ['mode' => $mode],
            occurredAt: new \DateTimeImmutable,
        ));

        return $eventId->value;
    }

    private function processor(
        OutboxConsumer|array $consumers,
        int $maxAttempts,
        int $retryBaseSeconds,
    ): OutboxProcessor {
        return new OutboxProcessor(
            app(InboxStore::class),
            is_array($consumers) ? $consumers : [$consumers],
            app(OutboxBacklogMonitor::class),
            maxAttempts: $maxAttempts,
            retryBaseSeconds: $retryBaseSeconds,
            retryMaxSeconds: $retryBaseSeconds,
        );
    }
}

final class ControllableOutboxConsumer implements OutboxConsumer
{
    private const string SENSITIVE_FAILURE = "secret=provider-token\nforged=<script>";

    /** @var array<string, int> */
    public array $calls = [
        'transient' => 0,
        'poison' => 0,
        'healthy' => 0,
    ];

    /** @param list<string> $failingModes */
    public function __construct(
        private readonly string $consumerName = 'test.failure',
        public array $failingModes = ['transient', 'poison'],
    ) {}

    public function name(): string
    {
        return $this->consumerName;
    }

    public function handle(OutgoingMessage $message): void
    {
        $mode = (string) $message->payload['mode'];
        $this->calls[$mode]++;

        DB::table('platform.spike_consumer_effects')->updateOrInsert(
            ['consumer_name' => "{$this->consumerName}.{$mode}"],
            ['effect_count' => 1],
        );

        if (in_array($mode, $this->failingModes, true)) {
            throw new \RuntimeException(self::SENSITIVE_FAILURE);
        }
    }
}
