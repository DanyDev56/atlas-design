<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\VerifyUserEmailHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifyEmailController extends Controller
{
    public function __construct(
        private readonly VerifyUserEmailHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid'],
            'token' => ['required', 'string'],
        ]);

        try {
            $result = $this->handler->handle($validated['user_id'], $validated['token']);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 422);
        }

        return response()->json($result);
    }
}
