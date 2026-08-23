<?php

declare(strict_types=1);

return [
    'default_plan' => [
        'code' => env('SUBSCRIPTIONS_DEFAULT_PLAN_CODE', 'atlas_solo'),
        'version' => (int) env('SUBSCRIPTIONS_DEFAULT_PLAN_VERSION', 1),
    ],
    'trial_days' => (int) env('SUBSCRIPTIONS_TRIAL_DAYS', 30),
    'gateway' => env('SUBSCRIPTIONS_GATEWAY', 'fake'),
    'checkout_enabled' => (bool) env('SUBSCRIPTIONS_CHECKOUT_ENABLED', false),
    'webhooks_enabled' => (bool) env('SUBSCRIPTIONS_WEBHOOKS_ENABLED', false),
    'fake_webhook_secret' => env('SUBSCRIPTIONS_FAKE_WEBHOOK_SECRET', ''),
    'enforcement_enabled' => (bool) env('SUBSCRIPTIONS_ENFORCEMENT_ENABLED', false),
];
