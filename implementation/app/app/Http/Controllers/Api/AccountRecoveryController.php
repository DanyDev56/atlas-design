<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\CompleteAccountRecoveryHandler;
use Atlas\Modules\Identity\Application\RequestAccountRecoveryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AccountRecoveryController extends Controller
{
    public function __construct(
        private readonly RequestAccountRecoveryHandler $requestRecovery,
        private readonly CompleteAccountRecoveryHandler $completeRecovery,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'debug_recovery_token' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->requestRecovery->handle(
                $validated['email'],
                $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], 409);
        }

        $payload = ['status' => 'accepted'];
        if (
            config('platform.development.debug_verification_tokens')
            && $request->boolean('debug_recovery_token')
            && isset($result['recovery_token'])
        ) {
            $payload['recovery_token'] = $result['recovery_token'];
        }

        return response()->json($payload);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:16'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            return response()->json($this->completeRecovery->handle(
                $validated['token'],
                $validated['password'],
                $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            ));
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Idempotency conflict.' ? 409 : 422;

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
