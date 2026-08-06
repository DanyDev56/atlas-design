<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\CreateSessionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoginController extends Controller
{
    public function __construct(
        private readonly CreateSessionHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->handler->handle($validated['email'], $validated['password']);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 401);
        }

        return response()->json([
            'session_id' => $result['session_id'],
            'user_id' => $result['user_id'],
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
        ]);
    }
}
