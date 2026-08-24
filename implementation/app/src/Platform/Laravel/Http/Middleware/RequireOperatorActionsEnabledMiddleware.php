<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireOperatorActionsEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('operations.backoffice.actions_enabled', false)
            && ! (bool) config('operations.backoffice.read_only', true)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'OperatorActionsDisabled',
            'messages' => ['Les actions opérateur sont désactivées sur cet environnement.'],
        ], 403);
    }
}
