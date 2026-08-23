<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Composition\Operations\ElevateOperatorSessionHandler;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorStepUpController extends Controller
{
    public function __construct(
        private readonly ElevateOperatorSessionHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'mfa_code' => ['required', 'string', 'max:32'],
        ]);
        $userId = (string) $request->attributes->get('operator_user_id');
        $sessionId = (string) $request->attributes->get('operator_session_id');
        $correlationId = $request->attributes->get('correlation_id');

        try {
            $result = $this->handler->handle(
                $userId,
                $sessionId,
                (string) $validated['email'],
                (string) $validated['password'],
                (string) $validated['mfa_code'],
                is_string($correlationId) ? $correlationId : null,
            );
        } catch (\DomainException) {
            $this->audit->record(
                action: 'operator.session.elevation-denied',
                result: 'Denied',
                operatorUserId: $userId,
                operatorSessionId: $sessionId,
                targetType: 'OperatorSession',
                targetIdHash: hash('sha256', $sessionId),
                correlationId: is_string($correlationId) ? $correlationId : null,
            );

            return response()->json([
                'error' => 'OperatorStepUpFailed',
                'messages' => ['La vérification renforcée a échoué.'],
            ], 401);
        }

        return response()->json($result);
    }
}
