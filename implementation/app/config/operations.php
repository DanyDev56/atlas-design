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
];
