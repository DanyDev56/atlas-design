<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorBearerSessionMiddleware
{
    public function __construct(
        private readonly PostgresOperatorSessionRepository $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Bearer ')) {
            return $this->unauthenticated();
        }

        $token = substr($authorization, 7);
        if ($token === '') {
            return $this->unauthenticated();
        }

        $session = $this->sessions->findActiveByTokenHash(
            PostgresOperatorSessionRepository::hashToken($token),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        if ($session === null
            || ! in_array(OperatorPermissionCatalog::BACKOFFICE_ACCESS, $session['permissions'], true)) {
            return $this->unauthenticated();
        }

        $request->attributes->set('operator_user_id', $session['user_id']);
        $request->attributes->set('operator_session_id', $session['id']);
        $request->attributes->set('operator_permissions', $session['permissions']);
        $request->attributes->set('operator_session_expires_at', $session['expires_at']);
        $request->attributes->set('operator_authentication_strength', $session['authentication_strength']);
        $request->attributes->set('operator_mfa_verified_at', $session['mfa_verified_at']);
        $request->attributes->set('operator_step_up_at', $session['step_up_at']);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json([
            'error' => 'OperatorUnauthenticated',
            'messages' => [],
        ], 401);
    }
}
