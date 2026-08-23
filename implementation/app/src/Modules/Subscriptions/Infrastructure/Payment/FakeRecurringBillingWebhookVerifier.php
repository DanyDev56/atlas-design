<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Infrastructure\Payment;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingWebhookVerifier;
use Atlas\Modules\Subscriptions\Domain\RecurringBillingEventType;
use Atlas\Modules\Subscriptions\Domain\VerifiedRecurringBillingEvent;

final class FakeRecurringBillingWebhookVerifier implements RecurringBillingWebhookVerifier
{
    public function verify(string $provider, string $payload, string $signature): VerifiedRecurringBillingEvent
    {
        if ($provider !== 'fake') {
            throw new \DomainException('Webhook provider unavailable.');
        }

        $secret = (string) config('subscriptions.fake_webhook_secret', '');
        if (strlen($secret) < 16) {
            throw new \DomainException('Webhook verifier unavailable.');
        }
        if ($signature === '' || ! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            throw new \DomainException('Invalid webhook signature.');
        }

        return $this->decodeTrusted($provider, $payload);
    }

    public function decodeTrusted(string $provider, string $payload): VerifiedRecurringBillingEvent
    {
        if ($provider !== 'fake') {
            throw new \DomainException('Webhook provider unavailable.');
        }

        try {
            $decoded = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            $data = $decoded['data'];
            $type = RecurringBillingEventType::from((string) $decoded['type']);

            return new VerifiedRecurringBillingEvent(
                provider: 'fake',
                providerEventId: (string) $decoded['id'],
                type: $type,
                workspaceId: (string) $data['workspace_id'],
                providerSubscriptionReference: (string) $data['subscription_reference'],
                planPriceId: (string) $data['plan_price_id'],
                occurredAt: new \DateTimeImmutable((string) $decoded['occurred_at']),
                currentPeriodStart: new \DateTimeImmutable((string) $data['current_period_start']),
                currentPeriodEnd: new \DateTimeImmutable((string) $data['current_period_end']),
                cancelAtPeriodEnd: (bool) ($data['cancel_at_period_end'] ?? false),
            );
        } catch (\Throwable) {
            throw new \DomainException('Malformed webhook payload.');
        }
    }
}
