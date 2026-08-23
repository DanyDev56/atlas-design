<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Infrastructure\Payment\FakeRecurringBillingWebhookVerifier;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

final class FakeRecurringBillingWebhookVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        $container->instance('config', new Repository([
            'subscriptions' => [
                'fake_webhook_secret' => 'atlas-unit-webhook-secret',
            ],
        ]));
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_valid_signature_produces_a_normalized_event(): void
    {
        $payload = $this->payload();
        $event = (new FakeRecurringBillingWebhookVerifier)->verify(
            'fake',
            $payload,
            hash_hmac('sha256', $payload, 'atlas-unit-webhook-secret'),
        );

        self::assertSame('event-1', $event->providerEventId);
        self::assertSame('workspace-1', $event->workspaceId);
        self::assertSame('subscription.activated', $event->type->value);
    }

    public function test_invalid_signature_is_rejected_before_payload_is_trusted(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid webhook signature.');

        (new FakeRecurringBillingWebhookVerifier)->verify('fake', $this->payload(), 'invalid');
    }

    private function payload(): string
    {
        return json_encode([
            'id' => 'event-1',
            'type' => 'subscription.activated',
            'occurred_at' => '2026-08-23T10:00:00+00:00',
            'data' => [
                'workspace_id' => 'workspace-1',
                'subscription_reference' => 'fake-subscription-1',
                'plan_price_id' => 'price-1',
                'current_period_start' => '2026-08-23T00:00:00+00:00',
                'current_period_end' => '2026-09-23T00:00:00+00:00',
                'cancel_at_period_end' => false,
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
