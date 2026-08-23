<?php

declare(strict_types=1);

return [
    'backoffice' => [
        'enabled' => (bool) env('BACKOFFICE_ENABLED', false),
        'allow_password_only_local' => (bool) env('BACKOFFICE_ALLOW_PASSWORD_ONLY_LOCAL', false),
        'read_only' => (bool) env('BACKOFFICE_READ_ONLY', true),
        'session_minutes' => (int) env('BACKOFFICE_SESSION_MINUTES', 30),
    ],
];
