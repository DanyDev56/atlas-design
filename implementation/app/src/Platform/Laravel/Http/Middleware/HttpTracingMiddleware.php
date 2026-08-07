<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Platform\Observability\Telemetry;
use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;

final class HttpTracingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Telemetry::isEnabled()) {
            return $next($request);
        }

        $tracer = Globals::tracerProvider()->getTracer(Telemetry::serviceName());
        $spanName = $request->method().' '.$request->path();

        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $correlationId = $request->attributes->get('correlation_id');
        if (is_string($correlationId) && $correlationId !== '') {
            $span->setAttribute('correlation_id', $correlationId);
        }

        $span->setAttribute('http.method', $request->method());
        $span->setAttribute('http.route', $request->path());
        $span->setAttribute('url.path', $request->path());

        $scope = $span->activate();

        try {
            $response = $next($request);
            $span->setAttribute('http.status_code', $response->getStatusCode());

            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }

            return $response;
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
