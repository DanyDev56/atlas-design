<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class ForceHttpsScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('platform.http.force_https')) {
            $request->server->set('HTTPS', 'on');
            $request->headers->set('X-Forwarded-Proto', 'https');
            URL::setRequest($request);
            URL::forceScheme('https');
        } else {
            URL::forceScheme(null);
        }

        return $next($request);
    }
}
