<?php

declare(strict_types=1);

namespace Tests\Unit\Subscriptions;

use Atlas\Modules\Subscriptions\Infrastructure\Payment\FakeRecurringBillingGateway;
use PHPUnit\Framework\TestCase;

final class FakeRecurringBillingGatewayTest extends TestCase
{
    public function test_same_idempotency_key_returns_same_preview_session(): void
    {
        $gateway = new FakeRecurringBillingGateway;

        $first = $gateway->createCheckoutSession(
            'workspace-1',
            'price-1',
            'request-1',
            'http://atlas.test/app/settings/subscription',
            'http://atlas.test/app/settings/subscription',
        );
        $second = $gateway->createCheckoutSession(
            'workspace-1',
            'price-1',
            'request-1',
            'http://atlas.test/app/settings/subscription',
            'http://atlas.test/app/settings/subscription',
        );

        self::assertSame($first->id, $second->id);
        self::assertSame($first->url, $second->url);
        self::assertSame($first->provider, $second->provider);
        self::assertSame('fake', $first->provider);
        self::assertStringContainsString('checkout=preview', $first->url);
    }
}
