<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireOperatorPermissionMiddleware
{
    public function __construct(private readonly PostgresOperatorAuditRepository $audit) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = $request->attributes->get('operator_permissions', []);
        if (is_array($permissions) && in_array($permission, $permissions, true)) {
            return $next($request);
        }

        $userId = $request->attributes->get('operator_user_id');
        $sessionId = $request->attributes->get('operator_session_id');
        $correlationId = $request->attributes->get('correlation_id');
        $this->audit->record(
            action: 'operator.permission.denied',
            result: 'Denied',
            operatorUserId: is_string($userId) ? $userId : null,
            operatorSessionId: is_string($sessionId) ? $sessionId : null,
            permission: $permission,
            targetType: 'OperatorRoute',
            correlationId: is_string($correlationId) ? $correlationId : null,
        );

        return response()->json([
            'error' => 'OperatorForbidden',
            'messages' => ['Permission opérateur insuffisante.'],
        ], 403);
    }
}
