<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperationsRuntimeRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecordApiRuntimeHeartbeat
{
    public function __construct(
        private readonly PostgresOperationsRuntimeRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') || $request->is('up')) {
            try {
                $this->recorder->heartbeat('api');
            } catch (\Throwable) {
                // Observability must not turn an otherwise valid request into an outage.
            }
        }

        return $next($request);
    }
}
