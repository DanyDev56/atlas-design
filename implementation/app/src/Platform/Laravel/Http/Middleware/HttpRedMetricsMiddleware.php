<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresHttpRedMetricsRecorder;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class HttpRedMetricsMiddleware
{
    public function __construct(private readonly PostgresHttpRedMetricsRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $statusCode = 500;

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();

            return $response;
        } catch (\Throwable $exception) {
            $statusCode = $this->exceptionStatusCode($exception);

            throw $exception;
        } finally {
            try {
                $route = $request->route();
                $template = is_object($route) && method_exists($route, 'uri')
                    ? '/'.ltrim((string) $route->uri(), '/')
                    : '/unmatched';
                $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
                $this->recorder->record($request->method(), $template, $statusCode, $durationMs);
            } catch (\Throwable $exception) {
                Log::warning('HTTP RED metric could not be recorded', [
                    'exception_type' => get_debug_type($exception),
                ]);
            }
        }
    }

    private function exceptionStatusCode(\Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ValidationException => 422,
            $exception instanceof ModelNotFoundException => 404,
            default => 500,
        };
    }
}
