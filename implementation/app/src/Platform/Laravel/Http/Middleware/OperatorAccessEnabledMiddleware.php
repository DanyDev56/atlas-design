<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorAccessEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('operations.backoffice.enabled', false)) {
            return response()->json([
                'error' => 'BackofficeUnavailable',
                'messages' => ['Le back-office est désactivé.'],
            ], 404);
        }

        $passwordOnlyAllowed = app()->environment(['local', 'testing'])
            && config('operations.backoffice.allow_password_only_local', false);

        if (! $passwordOnlyAllowed) {
            return response()->json([
                'error' => 'StrongAuthenticationRequired',
                'messages' => ['L’authentification opérateur renforcée n’est pas encore configurée.'],
            ], 503);
        }

        return $next($request);
    }
}
