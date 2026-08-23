<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Composition\Operations\CreateOperatorSessionHandler;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorLoginController extends Controller
{
    public function __construct(
        private readonly CreateOperatorSessionHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $correlationId = $request->attributes->get('correlation_id');

        try {
            $result = $this->handler->handle(
                (string) $validated['email'],
                (string) $validated['password'],
                is_string($correlationId) ? $correlationId : null,
            );
        } catch (\DomainException) {
            $this->audit->record(
                action: 'operator.session.open-denied',
                result: 'Denied',
                targetType: 'OperatorLogin',
                targetIdHash: hash('sha256', strtolower(trim((string) $validated['email']))),
                correlationId: is_string($correlationId) ? $correlationId : null,
            );

            return response()->json([
                'error' => 'InvalidOperatorCredentials',
                'messages' => ['Identifiants opérateur invalides.'],
            ], 401);
        }

        return response()->json($result);
    }
}
