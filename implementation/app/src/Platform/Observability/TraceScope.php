<?php

declare(strict_types=1);

namespace Atlas\Platform\Observability;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class TraceScope
{
    /**
     * @param array<string, bool|float|int|string|null> $attributes
     */
    public static function run(string $spanName, callable $callback, array $attributes = []): mixed
    {
        if (! Telemetry::isEnabled()) {
            return $callback();
        }

        $tracer = Globals::tracerProvider()->getTracer(Telemetry::serviceName());
        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $span->setAttribute($key, $value);
            }
        }

        $scope = $span->activate();

        try {
            return $callback();
        } catch (\Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
