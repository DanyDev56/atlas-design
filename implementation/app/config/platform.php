<?php

declare(strict_types=1);

return [
    'outbox' => [
        'backlog_warning_threshold' => (int) env('OUTBOX_BACKLOG_WARNING_THRESHOLD', 25),
    ],
    'rate_limits' => [
        'auth' => [
            'max_attempts' => (int) env('RATE_LIMIT_AUTH_MAX_ATTEMPTS', 20),
            'decay_seconds' => (int) env('RATE_LIMIT_AUTH_DECAY_SECONDS', 60),
        ],
    ],
];
