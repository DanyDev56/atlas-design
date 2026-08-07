<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\RevokeSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RevokeSessionController extends Controller
{
    public function __construct(
        private readonly RevokeSessionHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $actorUserId = (string) $request->attributes->get('authenticated_user_id');
        $sessionId = (string) $request->attributes->get('session_id');
        $requestId = $request->header('Idempotency-Key') ?? (string) Str::uuid();

        try {
            $result = $this->handler->handle($actorUserId, $sessionId, $requestId);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 422);
        }

        return response()->json($result);
    }
}
