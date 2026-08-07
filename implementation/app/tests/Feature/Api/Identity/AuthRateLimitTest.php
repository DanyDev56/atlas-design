<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Identity;

use Illuminate\Support\Facades\Config;
use Tests\Integration\IntegrationTestCase;

final class AuthRateLimitTest extends IntegrationTestCase
{
    public function test_login_is_rate_limited(): void
    {
        Config::set('platform.rate_limits.auth.max_attempts', 2);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'missing@crm.test',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', [
            'email' => 'missing@crm.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
