<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BearerSessionMiddleware
{
    public function __construct(
        private readonly PostgresSessionRepository $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Bearer ')) {
            return response()->json(['error' => 'Unauthenticated', 'messages' => []], 401);
        }

        $token = substr($authorization, 7);
        $session = $this->sessions->findActiveByTokenHash(
            PostgresSessionRepository::hashToken($token),
        );

        if ($session === null) {
            return response()->json(['error' => 'Unauthenticated', 'messages' => []], 401);
        }

        $request->attributes->set('authenticated_user_id', $session['user_id']);
        $request->attributes->set('session_id', $session['id']);

        return $next($request);
    }
}
