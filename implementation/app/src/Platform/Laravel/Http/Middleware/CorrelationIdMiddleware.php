<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Platform\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::resolve(
            $request->header('X-Correlation-Id') ?? $request->header('X-Request-Id'),
        );

        $request->attributes->set('correlation_id', $correlationId);
        Log::shareContext([
            'correlation_id' => $correlationId,
            'service' => env('OTEL_SERVICE_NAME', 'atlas-app'),
        ]);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
