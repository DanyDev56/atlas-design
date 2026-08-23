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
    'stripe' => [
        'secret_key' => env('SUBSCRIPTIONS_STRIPE_SECRET_KEY', ''),
        'webhook_secret' => env('SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET', ''),
        'price_ids' => [
            'a5d9e095-bcee-54ef-8b40-f47256997d40' => env('SUBSCRIPTIONS_STRIPE_MONTHLY_PRICE_ID', ''),
            'b7f839e4-f01b-5fd8-866d-6e55b2bb32e6' => env('SUBSCRIPTIONS_STRIPE_ANNUAL_PRICE_ID', ''),
        ],
    ],
    'past_due_grace_days' => is_numeric(env('SUBSCRIPTIONS_PAST_DUE_GRACE_DAYS'))
        ? (int) env('SUBSCRIPTIONS_PAST_DUE_GRACE_DAYS')
        : null,
    'enforcement_enabled' => (bool) env('SUBSCRIPTIONS_ENFORCEMENT_ENABLED', false),
];
