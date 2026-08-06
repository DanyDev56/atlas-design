<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BootstrapWorkspaceController extends Controller
{
    public function __construct(
        private readonly BootstrapFirstWorkspaceHandler $bootstrapHandler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null || $idempotencyKey === '') {
            return response()->json([
                'error' => 'ValidationError',
                'messages' => ['Idempotency-Key header is required.'],
            ], 422);
        }

        $userId = $request->attributes->get('authenticated_user_id');

        try {
            $result = $this->bootstrapHandler->handle(
                userId: $userId,
                workspaceName: $validated['name'],
                idempotencyKey: $idempotencyKey,
                correlationId: $request->attributes->get('correlation_id'),
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 422);
        }

        return response()->json($result, 201);
    }
}
