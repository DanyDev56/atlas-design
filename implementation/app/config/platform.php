<?php

declare(strict_types=1);

return [
    'outbox' => [
        'backlog_warning_threshold' => (int) env('OUTBOX_BACKLOG_WARNING_THRESHOLD', 25),
    ],
];
