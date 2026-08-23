<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\RevokeOperatorSessionHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorSessionController extends Controller
{
    public function __construct(
        private readonly RevokeOperatorSessionHandler $revoke,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('operator_user_id');
        $sessionId = (string) $request->attributes->get('operator_session_id');
        $correlationId = $request->attributes->get('correlation_id');

        $this->audit->record(
            action: 'operator.session.context-read',
            result: 'Allowed',
            operatorUserId: $userId,
            operatorSessionId: $sessionId,
            permission: OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            targetType: 'OperatorSession',
            targetIdHash: hash('sha256', $sessionId),
            correlationId: is_string($correlationId) ? $correlationId : null,
        );

        return response()->json([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'expires_at' => (string) $request->attributes->get('operator_session_expires_at'),
            'permissions' => $request->attributes->get('operator_permissions', []),
            'read_only' => (bool) config('operations.backoffice.read_only', true),
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('operator_user_id');
        $sessionId = (string) $request->attributes->get('operator_session_id');
        $correlationId = $request->attributes->get('correlation_id');

        $this->revoke->handle(
            $userId,
            $sessionId,
            is_string($correlationId) ? $correlationId : null,
        );

        return response()->json(['status' => 'Revoked']);
    }
}
