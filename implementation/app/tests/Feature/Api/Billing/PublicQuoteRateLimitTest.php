<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Illuminate\Support\Facades\Config;
use Tests\Integration\IntegrationTestCase;

final class PublicQuoteRateLimitTest extends IntegrationTestCase
{
    public function test_public_quote_accept_is_rate_limited(): void
    {
        Config::set('platform.rate_limits.public.max_attempts', 2);

        $url = '/api/public/workspaces/00000000-0000-4000-8000-000000000001/quotes/00000000-0000-4000-8000-000000000002/accept';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson($url, [
                'public_token' => str_repeat('a', 32),
                'expected_revision' => 1,
            ])->assertStatus(401);
        }

        $this->postJson($url, [
            'public_token' => str_repeat('a', 32),
            'expected_revision' => 1,
        ])->assertStatus(429);
    }
}
