<?php

declare(strict_types=1);

return [
    'http' => [
        'force_https' => (bool) env('ATLAS_FORCE_HTTPS', false),
    ],
    'development' => [
        'routes_enabled' => (bool) env(
            'ATLAS_DEVELOPMENT_ROUTES',
            env('APP_ENV', 'production') === 'local',
        ),
        'debug_verification_tokens' => (bool) env(
            'ATLAS_DEBUG_VERIFICATION_TOKENS',
            env('APP_ENV', 'production') === 'local',
        ),
    ],
    'outbox' => [
        'backlog_warning_threshold' => (int) env('OUTBOX_BACKLOG_WARNING_THRESHOLD', 25),
        'alert_webhook_url' => env('OUTBOX_BACKLOG_ALERT_WEBHOOK_URL'),
        'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('OUTBOX_RETRY_BASE_SECONDS', 5),
        'retry_max_seconds' => (int) env('OUTBOX_RETRY_MAX_SECONDS', 300),
    ],
    'rate_limits' => [
        'auth' => [
            'max_attempts' => (int) env('RATE_LIMIT_AUTH_MAX_ATTEMPTS', 20),
            'decay_seconds' => (int) env('RATE_LIMIT_AUTH_DECAY_SECONDS', 60),
        ],
        'public' => [
            'max_attempts' => (int) env('RATE_LIMIT_PUBLIC_MAX_ATTEMPTS', 30),
        ],
    ],
    'retention' => [
        'sessions_days' => (int) env('RETENTION_SESSIONS_DAYS', 30),
        'outbox_dispatched_days' => (int) env('RETENTION_OUTBOX_DISPATCHED_DAYS', 90),
        'idempotency_keys_days' => (int) env('RETENTION_IDEMPOTENCY_DAYS', 30),
    ],
];
