<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\RegisterUserHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RegisterUserController extends Controller
{
    public function __construct(
        private readonly RegisterUserHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
            'debug_verification_token' => ['sometimes', 'boolean'],
        ]);

        $requestId = $request->header('Idempotency-Key') ?? (string) Str::uuid();

        try {
            $result = $this->handler->handle(
                email: $validated['email'],
                displayName: $validated['display_name'],
                password: $validated['password'],
                requestId: $requestId,
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 422);
        }

        $payload = [
            'user_id' => $result['user_id'],
            'status' => $result['status'],
        ];

        if (
            config('platform.development.debug_verification_tokens')
            && $request->boolean('debug_verification_token')
            && isset($result['verification_token'])
        ) {
            $payload['verification_token'] = $result['verification_token'];
        }

        return response()->json($payload, 201);
    }
}
