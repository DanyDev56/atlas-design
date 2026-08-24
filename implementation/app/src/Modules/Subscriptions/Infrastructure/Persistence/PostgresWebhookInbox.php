<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Persistence;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingWebhookInbox;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;
use Atlas\Modules\Subscriptions\Infrastructure\Payment\ConfiguredBillingEnvironment;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;

final class PostgresWebhookInbox implements RecurringBillingWebhookInbox
{
    public function __construct(
        private readonly ConfiguredBillingEnvironment $environment,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, inserted: bool}
     */
    public function record(VerifiedRecurringBillingEvent $event, string $payloadHash, array $payload): array
    {
        $inserted = DB::table('subscriptions.webhook_inbox')->insertOrIgnore([
            'id' => UuidGenerator::generate(),
            'provider' => $event->provider,
            'billing_environment' => $this->environment->current(),
            'provider_event_id' => $event->providerEventId,
            'event_type' => $event->type->value,
            'provider_subscription_reference' => $event->providerSubscriptionReference,
            'payload_hash' => $payloadHash,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:sP'),
            'status' => 'Received',
            'attempts' => 0,
            'received_at' => now('UTC'),
        ]);

        if ($inserted === 1) {
            return ['status' => 'Received', 'inserted' => true];
        }

        $existing = $this->find($event->provider, $event->providerEventId);
        if ($existing === null || ! hash_equals((string) $existing['payload_hash'], $payloadHash)) {
            throw new \DomainException('Webhook event id conflict.');
        }

        return ['status' => (string) $existing['status'], 'inserted' => false];
    }

    /** @return array<string, mixed>|null */
    public function find(string $provider, string $providerEventId): ?array
    {
        $row = DB::table('subscriptions.webhook_inbox')
            ->where('provider', $provider)
            ->where('provider_event_id', $providerEventId)
            ->first();

        return $row === null ? null : (array) $row;
    }

    public function claim(string $provider, string $providerEventId): bool
    {
        return DB::table('subscriptions.webhook_inbox')
            ->where('provider', $provider)
            ->where('provider_event_id', $providerEventId)
            ->whereIn('status', ['Received', 'Deferred', 'Failed'])
            ->update(['status' => 'Processing']) === 1;
    }

    public function mark(string $provider, string $providerEventId, string $status, ?string $reason = null): void
    {
        DB::table('subscriptions.webhook_inbox')
            ->where('provider', $provider)
            ->where('provider_event_id', $providerEventId)
            ->update([
                'status' => $status,
                'attempts' => DB::raw('attempts + 1'),
                'failure_reason' => $reason,
                'processed_at' => in_array($status, ['Processed', 'Ignored'], true) ? now('UTC') : null,
            ]);
    }
}
