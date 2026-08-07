<?php

declare(strict_types=1);

return [
    'service_name' => env('OTEL_SERVICE_NAME', 'atlas-app'),
    'traces_exporter' => env('OTEL_TRACES_EXPORTER', 'none'),
    'exporter_otlp_endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
];
