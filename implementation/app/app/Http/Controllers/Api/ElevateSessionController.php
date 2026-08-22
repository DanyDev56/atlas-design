<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\ElevateSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ElevateSessionController extends Controller
{
    public function __construct(
        private readonly ElevateSessionHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->handler->handle(
                actorUserId: (string) $request->attributes->get('authenticated_user_id'),
                sessionId: (string) $request->attributes->get('session_id'),
                password: $validated['password'],
                requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            );
        } catch (\DomainException $exception) {
            $status = match ($exception->getMessage()) {
                'Invalid credentials.', 'User cannot authenticate.' => 401,
                'Unauthorized.' => 403,
                'Idempotency conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }

        return response()->json($result);
    }
}
