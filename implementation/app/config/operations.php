<?php

declare(strict_types=1);

return [
    'backoffice' => [
        'enabled' => (bool) env('BACKOFFICE_ENABLED', false),
        'allow_password_only_local' => (bool) env('BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL', false),
        'require_mfa' => (bool) env('BACKOFFICE_REQUIRE_MFA', true),
        'allow_totp_external' => (bool) env('BACKOFFICE_ALLOW_TOTP_EXTERNAL', false),
        'read_only' => (bool) env('BACKOFFICE_READ_ONLY', true),
        'actions_enabled' => (bool) env('BACKOFFICE_ACTIONS_ENABLED', false),
        'session_minutes' => (int) env('BACKOFFICE_SESSION_MINUTES', 30),
        'step_up_minutes' => (int) env('BACKOFFICE_STEP_UP_MINUTES', 10),
        'totp_issuer' => (string) env('BACKOFFICE_TOTP_ISSUER', 'Atlas Back-office'),
    ],
    'beta' => [
        'blocked_after_days' => (int) env('BACKOFFICE_BETA_BLOCKED_AFTER_DAYS', 7),
    ],
    'http' => [
        'window_minutes' => (int) env('OPERATIONS_HTTP_WINDOW_MINUTES', 5),
        'minimum_requests' => (int) env('OPERATIONS_HTTP_MINIMUM_REQUESTS', 20),
        'error_rate_threshold_percent' => (float) env('OPERATIONS_HTTP_ERROR_RATE_THRESHOLD_PERCENT', 10),
        'retention_days' => (int) env('OPERATIONS_HTTP_RETENTION_DAYS', 30),
    ],
    'alerts' => [
        'webhook_url' => env('OPERATIONS_ALERT_WEBHOOK_URL'),
        'repeat_minutes' => (int) env('OPERATIONS_ALERT_REPEAT_MINUTES', 60),
        'runtime_stale_seconds' => (int) env('OPERATIONS_RUNTIME_STALE_SECONDS', 180),
        'backup_stale_seconds' => (int) env('OPERATIONS_BACKUP_STALE_SECONDS', 90000),
    ],
];
