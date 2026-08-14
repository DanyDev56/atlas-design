<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DevelopmentOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('platform.development.routes_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
