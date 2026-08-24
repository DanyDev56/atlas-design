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
            'authentication_strength' => (string) $request->attributes->get('operator_authentication_strength'),
            'mfa_verified_at' => $request->attributes->get('operator_mfa_verified_at'),
            'step_up_expires_at' => $this->stepUpExpiresAt($request->attributes->get('operator_step_up_at')),
            'read_only' => (bool) config('operations.backoffice.read_only', true),
            'actions_enabled' => (bool) config('operations.backoffice.actions_enabled', false)
                && ! (bool) config('operations.backoffice.read_only', true),
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

    private function stepUpExpiresAt(mixed $stepUpAt): ?string
    {
        if (! is_string($stepUpAt) || $stepUpAt === '') {
            return null;
        }

        try {
            $verifiedAt = new \DateTimeImmutable($stepUpAt);
        } catch (\Throwable) {
            return null;
        }

        $minutes = max(1, min(30, (int) config('operations.backoffice.step_up_minutes', 10)));

        return $verifiedAt->modify('+'.$minutes.' minutes')->format(DATE_ATOM);
    }
}
